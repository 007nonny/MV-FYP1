from fastapi import FastAPI, UploadFile, File
from fastapi.responses import JSONResponse
import torch
import torchvision.transforms as transforms
from torchvision import models
from PIL import Image
import json
import io
import os

app = FastAPI()

# ---- Family mapping table (loaded from JSON) ----
with open("/home/kali/Desktop/FYP1/MalwareImageRecognitionFYP1/models/family_mapping.json", "r") as f:
    _raw_map = json.load(f)
# Normalise severity to lowercase for internal use
FAMILY_MAP = {
    family: (info["category"], info["severity"].lower())
    for family, info in _raw_map.items()
}

# ---- Load labels ----
with open("/home/kali/Desktop/FYP1/MalwareImageRecognitionFYP1/models/labels.json", "r") as f:
    labels = json.load(f)
idx_to_label = {v: k for k, v in labels.items()}
num_classes = len(labels)

# ---- Load model ----
device = torch.device("cpu")

model = models.mobilenet_v2(weights=None)
model.classifier[1] = torch.nn.Linear(
    model.classifier[1].in_features, num_classes
)
model.load_state_dict(torch.load("/home/kali/Desktop/FYP1/MalwareImageRecognitionFYP1/models/model.pt", map_location=device))
model.eval()

# ---- Image transform ----
transform = transforms.Compose([
    transforms.Resize((224, 224)),
    transforms.ToTensor(),
    transforms.Normalize([0.485, 0.456, 0.406], [0.229, 0.224, 0.225])
])

@app.post("/analyze")
async def analyze(file: UploadFile = File(...)):
    try:
        image_bytes = await file.read()
        image = Image.open(io.BytesIO(image_bytes)).convert("RGB")
        image = transform(image).unsqueeze(0)

        with torch.no_grad():
            outputs = model(image)
            probabilities = torch.nn.functional.softmax(outputs, dim=1)
            confidence, pred_idx = probabilities.max(1)
            confidence = confidence.item()
            pred_idx = pred_idx.item()

        # Confidence threshold for detection
        CONFIDENCE_THRESHOLD = 0.50  # 50% confidence required
        
        confidence_pct = f"{confidence * 100:.1f}%"
        
        detected_type = idx_to_label[pred_idx]

        if confidence < CONFIDENCE_THRESHOLD:
            # Low confidence — still show the predicted family, but mark as Uncertain
            trojan_type = "Uncertain"
            severity = "unknown"
            trojan_subtype = detected_type
        else:
            # Look up family in mapping table (family_mapping.json)
            if detected_type in FAMILY_MAP:
                trojan_type, severity = FAMILY_MAP[detected_type]
            else:
                trojan_type = "Other Malware"
                severity = "medium"
            trojan_subtype = detected_type  # Always show specific family name

        return JSONResponse({
            "trojan_type": trojan_type,
            "trojan_subtype": trojan_subtype,
            "severity": severity,
            "confidence": confidence_pct
        })

    except Exception as e:
        return JSONResponse(
            status_code=500,
            content={"error": str(e)}
        )

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=5000)