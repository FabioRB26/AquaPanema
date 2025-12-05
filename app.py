# app.py
from flask import Flask, request, jsonify
import tensorflow as tf
import numpy as np
import json

# === carregamento do modelo e classes ===
MODEL_PATH = "model_mobilenetv2_paranapanema.h5"
CLASSES_PATH = "class_indices.json"
IMG_SIZE = (224, 224)

model = tf.keras.models.load_model(MODEL_PATH, compile=False)
with open(CLASSES_PATH, "r", encoding="utf-8") as f:
    class_indices = json.load(f)
idx_to_class = {v: k for k, v in class_indices.items()}

app = Flask(__name__)
app.config["MAX_CONTENT_LENGTH"] = 10 * 1024 * 1024  # até 10MB

def load_and_preprocess_bytes(img_bytes: bytes):
    img = tf.image.decode_image(img_bytes, channels=3, expand_animations=False)
    img = tf.image.convert_image_dtype(img, tf.float32)
    img = tf.image.resize(img, IMG_SIZE, method=tf.image.ResizeMethod.BICUBIC)
    # mesmo preprocess do treino (MobileNetV2)
    img = tf.keras.applications.mobilenet_v2.preprocess_input(img * 255.0)
    return tf.expand_dims(img, 0)  # shape (1, 224, 224, 3)

@app.route("/health", methods=["GET"])
def health():
    return jsonify({"status": "ok", "classes": len(idx_to_class)}), 200

@app.route("/predict", methods=["POST"])
def predict():
    if "file" not in request.files:
        return jsonify({"error": "file field missing"}), 400
    file = request.files["file"]
    data = file.read()
    if not data:
        return jsonify({"error": "empty file"}), 400

    x = load_and_preprocess_bytes(data)
    probs = model.predict(x, verbose=0)[0]  # vetor com N classes
    top_idx = int(np.argmax(probs))
    scientific_name = idx_to_class.get(top_idx, None)
    confidence = float(probs[top_idx])

    # opcional: top-3
    top3_idx = np.argsort(-probs)[:3].tolist()
    top3 = [
        {"scientific_name": idx_to_class[i], "confidence": float(probs[i])}
        for i in top3_idx
    ]

    return jsonify({
        "scientific_name": scientific_name,
        "confidence": confidence,
        "top3": top3
    }), 200

if __name__ == "__main__":
    # rode em 0.0.0.0 se quiser acessar de outra máquina na rede
    app.run(host="127.0.0.1", port=8000, debug=False)
