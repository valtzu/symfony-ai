import { Controller } from '@hotwired/stimulus';
import { GoogleGenAI } from "@google/genai";
import '../styles/audio_stream.css';

async function decodeAudioData(data, ctx, sampleRate, numChannels){
    const buffer = ctx.createBuffer(
        numChannels,
        data.length / 2 / numChannels,
        sampleRate,
    );

    const dataInt16 = new Int16Array(data.buffer);
    const l = dataInt16.length;
    const dataFloat32 = new Float32Array(l);
    for (let i = 0; i < l; i++) {
        dataFloat32[i] = dataInt16[i] / 32768.0;
    }
    if (numChannels === 0) {
        buffer.copyToChannel(dataFloat32, 0);
    } else {
        for (let i = 0; i < numChannels; i++) {
            const channel = dataFloat32.filter(
                (_, index) => index % numChannels === i,
            );
            buffer.copyToChannel(channel, i);
        }
    }

    return buffer;
}

function encode(bytes) {
    let binary = '';
    const len = bytes.byteLength;
    for (let i = 0; i < len; i++) {
        binary += String.fromCharCode(bytes[i]);
    }
    return btoa(binary);
}

function createBlob(data) {
    const l = data.length;
    const int16 = new Int16Array(l);
    for (let i = 0; i < l; i++) {
        // convert float32 -1 to 1 to int16 -32768 to 32767
        int16[i] = data[i] * 32768;
    }

    return {
        data: encode(new Uint8Array(int16.buffer)),
        mimeType: 'audio/pcm;rate=16000',
    };
}

function decode(base64) {
    const binaryString = atob(base64);
    const len = binaryString.length;
    const bytes = new Uint8Array(len);
    for (let i = 0; i < len; i++) {
        bytes[i] = binaryString.charCodeAt(i);
    }
    return bytes;
}

export default class extends Controller {
    static targets = ['startButton', 'stopButton', "inputBucket", "outputBucket", 'status'];
    static values = {
        apiKey: String,
    };

    connect() {
        this.ai = new GoogleGenAI({ apiKey: this.apiKeyValue, apiVersion: 'v1alpha', httpOptions: { apiVersion: 'v1alpha' }, });
        /** @var {AudioContext} */
        this.inputAudioContext = new (window.AudioContext || window.webkitAudioContext)({sampleRate: 16000});
        this.outputAudioContext = new (window.AudioContext || window.webkitAudioContext)({sampleRate: 24000});
        this.nextStartTime = this.outputAudioContext.currentTime;
        this.inputNode = this.inputAudioContext.createGain();
        this.outputNode = this.outputAudioContext.createGain();
        this.outputNode.connect(this.outputAudioContext.destination);
        this.sources = new Set();

        this.analyser = null;
        this.inputAnimation = null;
        this.outputAnimation = null;

        this.liveSession = null;
        this.isSpeaking = false;

        this.statusTarget.textContent = 'Ready.';
    }

    disconnect() {
        if (this.inputAnimation) {
            cancelAnimationFrame(this.inputAnimation);
        }
        if (this.outputAnimation) {
            cancelAnimationFrame(this.outputAnimation);
        }
        if (this.inputAudioContext) {
            this.inputAudioContext.close();
        }
        this.endStream();
    }

    async start() {
        if (this.isRecording) {
            return;
        }

        this.inputAudioContext.resume();

        this.updateStatus('Requesting microphone access...');

        if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
            this.updateStatus('Awaiting microphone permission...');
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                this.handleSuccess(stream);
            } catch (err) {
                this.handleError(err);
            }
        } else {
            this.updateStatus('getUserMedia not supported on your browser!');
        }
    }

    stop() {
        for (const source of this.sources.values()) {
            source.stop();
            this.sources.delete(source);
        }
        this.endStream();
        this.updateStatus('Streaming stopped. Click Start to begin again.');
        this.startButtonTarget.style.display = 'block';
        this.stopButtonTarget.style.display = 'none';
    }

    handleSuccess(stream) {
        this.statusTarget.textContent = 'Microphone access granted.';
        this.startButtonTarget.style.display = 'none';
        this.stopButtonTarget.style.display = 'block';

        const analyser = this.inputAudioContext.createAnalyser();

        /** @var {MediaStreamAudioSourceNode} */
        this.sourceNode = this.inputAudioContext.createMediaStreamSource(stream);
        this.sourceNode.connect(this.inputNode);

        const bufferSize = 16384;
        this.scriptProcessorNode = this.inputAudioContext.createScriptProcessor(
            bufferSize,
            1,
            1,
        );

        this.scriptProcessorNode.onaudioprocess = (audioProcessingEvent) => {
            if (!this.isRecording || !this.liveSession) {
                console.log('Doing nothing since we are not recording');
                return;
            }

            const inputBuffer = audioProcessingEvent.inputBuffer;
            const pcmData = inputBuffer.getChannelData(0);

            // Check if there is significant audio input (e.g., above a certain threshold)
            const volume = pcmData.reduce((sum, value) => sum + Math.abs(value), 0) / pcmData.length;
            const volumeThreshold = 0.01; // Adjust this value as needed

            if (volume > volumeThreshold) {
                this.isSpeaking = true;
                this.liveSession.sendRealtimeInput({media: createBlob(pcmData)});
            } else {
                if (this.isSpeaking) {
                    this.liveSession.sendRealtimeInput({media: createBlob(pcmData)});
                }
                setTimeout(() => this.isSpeaking = false, 500);
            }
        };

        this.sourceNode.connect(this.scriptProcessorNode);
        this.scriptProcessorNode.connect(this.inputAudioContext.destination);

        this.isRecording = true;
        this.updateStatus('🔴 Recording... Capturing PCM chunks.');

        analyser.fftSize = 2048;
        analyser.minDecibels = -90;
        analyser.smoothingTimeConstant = 0.7;
        this.sourceNode.connect(analyser);

        this.updateVolume(this.inputBucketTargets, analyser, this.inputAudioContext, null, animationId => this.inputAnimation = animationId);
        return this.startLiveSession();
    }

    handleError(err) {
        this.statusTarget.textContent = 'Microphone access denied. Please enable it in your browser settings.';
        console.error('The following error occurred: ' + err);
    }

    updateVolume(targets, analyser, audioContext, buffer, callback) {
        buffer ??= new Uint8Array(analyser.frequencyBinCount);
        analyser.getByteFrequencyData(buffer);
        const NUM_BUCKETS = Math.ceil(targets.length / 2);
        const fftSize = analyser.fftSize;
        const sampleRate = audioContext.sampleRate;
        const minFreq = 80;
        const maxFreq = Math.min(8000, sampleRate / 2);
        const logBuckets = new Array(NUM_BUCKETS).fill(0);

        for (let i = 0; i < NUM_BUCKETS; i++) {
            const bucketStartFreq = minFreq * Math.pow(maxFreq / minFreq, i / NUM_BUCKETS);
            const bucketEndFreq = minFreq * Math.pow(maxFreq / minFreq, (i + 1) / NUM_BUCKETS);
            const startIndex = Math.round(bucketStartFreq * fftSize / sampleRate);
            const endIndex = Math.round(bucketEndFreq * fftSize / sampleRate);

            if (endIndex <= startIndex) {
                logBuckets[i] = 0;
                continue;
            }

            const slice = buffer.slice(startIndex, endIndex);
            const sum = slice.reduce((sum, current) => sum + current, 0);
            logBuckets[i] = sum / slice.length;
        }

        targets.forEach((bar, i) => {
            const average = logBuckets[Math.abs(NUM_BUCKETS - i - 1)];
            const barHeight = (Math.max(0.1, Math.min(1.0, (Math.log10(average + 1) / Math.log10(256)))) - 0.1) / 0.9 * 100;
            bar.style.transform = `scaleY(${barHeight / 100})`;
            bar.style.opacity = `${barHeight}%`;
        });

        callback(requestAnimationFrame(() => this.updateVolume(targets, analyser, audioContext, buffer, callback)));
    }

    async startLiveSession() {
        try {
            this.liveSession = await this.ai.live.connect({
                model: 'gemini-2.5-flash-preview-native-audio-dialog',
                // model: 'gemini-2.0-flash-live-001',
                callbacks: {
                    onopen: () => {
                        console.log("onopen");
                        this.statusTarget.textContent = 'Connected to Gemini Live. Speak to start the conversation.';
                    },
                    onclose: () => {
                        console.log("onclose");
                        this.statusTarget.textContent = 'Disconnected from Gemini Live.';
                        this.endStream();
                    },
                    onerror: (err) => {
                        console.error('Gemini Live API error:', err);
                        this.statusTarget.textContent = 'Error with Gemini Live session. See console for details.';
                        this.endStream();
                    },
                    onmessage: async (message) => {
                        if (message.goAway) {
                            console.log('Server will go away soon');
                        }
                        if (message.setupComplete) {
                            console.log('Setup complete');
                            return;
                        }
                        const audio = message.serverContent?.modelTurn?.parts[0]?.inlineData;

                        if (audio) {
                            this.nextStartTime = Math.max(
                                this.nextStartTime,
                                this.outputAudioContext.currentTime,
                            );

                            const audioBuffer = await decodeAudioData(
                                decode(audio.data),
                                this.outputAudioContext,
                                24000,
                                1,
                            );
                            const source = this.outputAudioContext.createBufferSource();
                            source.buffer = audioBuffer;
                            source.connect(this.outputNode);
                            source.addEventListener('ended', () => this.sources.delete(source));
                            source.start(this.nextStartTime);
                            this.nextStartTime += audioBuffer.duration;
                            this.sources.add(source);
                            this.outputAudioContext.resume();
                        }

                        if (message.serverContent?.interrupted) {
                            for (const source of this.sources.values()) {
                                source.stop();
                                this.sources.delete(source);
                            }
                            this.nextStartTime = 0;
                        }
                    },
                },
            });

            const analyser = this.outputAudioContext.createAnalyser();
            analyser.fftSize = 2048;
            analyser.minDecibels = -90;
            analyser.smoothingTimeConstant = 0.7;
            this.outputNode.connect(analyser);
            this.updateVolume(this.outputBucketTargets, analyser, this.outputAudioContext, null, animationId => this.outputAnimation = animationId);
        } catch (error) {
            console.error("Failed to connect to Gemini Live:", error);
            this.updateStatus('Failed to connect to Gemini Live. Please check your API key and permissions.');
            this.endStream();
        }
    }

    updateStatus(status) {
        this.statusTarget.textContent = status;
    }

    endStream() {
        this.sourceNode.mediaStream.getTracks().at(0).stop();
        this.sourceNode.disconnect();
        // this.inputAudioContext.close();
        this.outputAudioContext.close();
        if (this.liveSession) {
            this.liveSession.close();
            this.liveSession = null;
        }
    }
}
