from flask import Flask, request, jsonify
from faster_whisper import WhisperModel
import tempfile
import subprocess
import os

app = Flask(__name__)
model = WhisperModel("base", device="cpu", compute_type="int8")  # Use CPU with int8 quantization

@app.route("/transcribe", methods=["POST"])
def transcribe():
    try:
        raw_audio = request.data
        if not raw_audio:
            return jsonify({"error": "Empty audio data received"}), 400

        # Save audio to .ogg file instead of .webm
        tmpogg = tempfile.NamedTemporaryFile(delete=False, suffix=".ogg")
        tmpogg.write(raw_audio)
        tmpogg.close()
        ogg_path = tmpogg.name

        # Convert to wav using ffmpeg with correct format hint
        wav_path = ogg_path.replace(".ogg", ".wav")
        ffmpeg_cmd = [
            "ffmpeg",
            "-y",
            "-f", "webm",  # or "webm"
            "-i", ogg_path,
            "-err_detect", "ignore_err",  # <- Try this
            "-ar", "16000",
            "-ac", "1",
            "-f", "wav",
            wav_path
        ]

        result = subprocess.run(ffmpeg_cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE)

        if result.returncode != 0:
            ffmpeg_error = result.stderr.decode("utf-8")
            return jsonify({"error": f"ffmpeg conversion failed:\n{ffmpeg_error}"}), 500

        # Transcribe WAV audio
        segments, _ = model.transcribe(wav_path, vad_filter=True)
        text = " ".join([seg.text.strip() for seg in segments])

        # Cleanup
        os.remove(ogg_path)
        os.remove(wav_path)

        return jsonify({"text": text})

    except Exception as e:
        return jsonify({"error": str(e)}), 500

if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5005)
