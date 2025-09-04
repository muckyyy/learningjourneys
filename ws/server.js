require('dotenv').config();
const WebSocket = require('ws');
const express = require('express');
const http = require('http');
const fs = require('fs');
const os = require('os');
const path = require('path');
const fetch = require('node-fetch');
const { v4: uuidv4 } = require('uuid');
const FormData = require('form-data');
const ffmpeg = require('fluent-ffmpeg');

const app = express();
const server = http.createServer(app);
const wss = new WebSocket.Server({ server });

const PORT = process.env.PORT || 3000;
const OPENAI_API_KEY = process.env.OPENAI_API_KEY;
const OPENAI_URL = 'https://api.openai.com/v1/audio/transcriptions';

const THREE_SEC_BYTES = 12000;  // ≈ 3 seconds of opus audio
const ONE_SEC_BYTES = 4000;     // Discard if < 1s
let previousTranscript = '';

// 👂 Function to detect silence based on RMS threshold
function isSilent(buffer, threshold = 0.57) {
    const view = new Int16Array(buffer.buffer, buffer.byteOffset, buffer.length / 2);
    let sumSquares = 0;
    for (let i = 0; i < view.length; i++) {
        sumSquares += view[i] * view[i];
    }
    const rms = Math.sqrt(sumSquares / view.length) / 32768;
    return rms < threshold;
}

// 🤖 Simple hallucination detection: drop if transcription < 4 words
function isLikelyHallucination(text) {
    const wordCount = text.trim().split(/\s+/).length;
    return wordCount <= 4;
}

wss.on('connection', function connection(ws) {
    console.log('🔗 WebSocket connection established');

    let audioChunks = [];
    let bufferedBytes = 0;

    ws.on('message', async (message, isBinary) => {
        if (!isBinary && typeof message === 'string') {
            try {
                const data = JSON.parse(message);
                if (data.type === 'audio-end') {
                    console.log('🎤 Audio stream ended');
                    return;
                }
            } catch (e) {
                return;
            }
        }

        if (isBinary) {
            audioChunks.push(message);
            bufferedBytes += message.length;

            if (bufferedBytes >= THREE_SEC_BYTES) {
                const audioBuffer = Buffer.concat(audioChunks);
                audioChunks = [];
                bufferedBytes = 0;

                if (audioBuffer.length < ONE_SEC_BYTES) {
                    console.log('⚠️ Discarded chunk <1s');
                    return;
                }

                if (isSilent(audioBuffer)) {
                    console.log('🤫 Skipped silent chunk (RMS too low)');
                    return;
                }

                console.log(`📦 Transmitting ${audioBuffer.length} bytes (~3s)`);

                const tmpWebm = path.join(os.tmpdir(), `audio-${uuidv4()}.webm`);
                const tmpFlac = tmpWebm.replace('.webm', '.flac');

                try {
                    fs.writeFileSync(tmpWebm, audioBuffer);

                    await new Promise((resolve, reject) => {
                        ffmpeg(tmpWebm)
                            .inputFormat('webm')
                            .audioChannels(1)
                            .audioFrequency(16000)
                            .output(tmpFlac)
                            .on('end', resolve)
                            .on('error', reject)
                            .run();
                    });

                    const form = new FormData();
                    form.append('file', fs.createReadStream(tmpFlac));
                    form.append('model', 'whisper-1');
                    form.append('temperature', '0.0');

                    // Use prior transcript ONLY if not empty
                    const recentTranscript = previousTranscript.slice(-1000);
                    if (recentTranscript.length > 10) {
                        form.append('prompt', recentTranscript);
                    }

                    const response = await fetch(OPENAI_URL, {
                        method: 'POST',
                        headers: {
                            Authorization: `Bearer ${OPENAI_API_KEY}`
                        },
                        body: form
                    });

                    const result = await response.json();

                    if (result.text) {
                        if (isLikelyHallucination(result.text)) {
                            console.warn('🤖 Ignored short transcription (likely hallucination):', result.text);
                            previousTranscript = ''; // Reset context to avoid repetition
                            return; // skip sending this to client
                        }

                        console.log('📝 Transcribed:', result.text);
                        previousTranscript += ' ' + result.text;

                        ws.send(JSON.stringify({
                            type: 'transcription',
                            text: result.text
                        }));
                    } else {
                        console.warn('⚠️ OpenAI error:', result);
                        ws.send(JSON.stringify({
                            type: 'error',
                            error: result.error?.message || 'Unknown error'
                        }));
                    }

                } catch (err) {
                    console.error('❌ Transcription error:', err.message || err);
                    ws.send(JSON.stringify({
                        type: 'error',
                        error: 'Transcription failed: ' + err.message
                    }));
                } finally {
                    if (fs.existsSync(tmpWebm)) fs.unlinkSync(tmpWebm);
                    if (fs.existsSync(tmpFlac)) fs.unlinkSync(tmpFlac);
                }
            }
        }
    });

    ws.on('close', () => {
        console.log('🔌 WebSocket closed');
        previousTranscript = ''; // reset for next user
    });
});

server.listen(PORT, () => {
    console.log(`✅ WebSocket server running at ws://localhost:${PORT}`);
});
