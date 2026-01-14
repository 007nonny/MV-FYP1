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
        
        if confidence < CONFIDENCE_THRESHOLD:
            trojan_type = "Other Malware"
            severity = "medium"
            trojan_subtype = "Unknown"
        else:
            detected_type = idx_to_label[pred_idx]
            
            # Map to three categories: Trojan, Other Malware, or Benign
            if "trojan" in detected_type.lower():
                trojan_type = "Trojan"
                severity = "high"
                trojan_subtype = detected_type  # Specific trojan family
            elif detected_type.lower() == "benign":
                trojan_type = "Benign"
                severity = "safe"
                trojan_subtype = "N/A"
            else:
                # All other malware types
                trojan_type = "Other Malware"
                severity = "medium"
                trojan_subtype = detected_type  # Specific malware family

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