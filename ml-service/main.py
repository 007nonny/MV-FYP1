from fastapi import FastAPI, UploadFile, File
from fastapi.responses import JSONResponse
import torch
import torchvision.transforms as transforms
from torchvision import models
from PIL import Image
import json
import io

app = FastAPI()

# ---- Severity policy ----
# High   = stronger persistence / wider damage / botnet or downloader behavior
# Medium = trojan families with moderate risk
# Low    = obfuscators or less directly harmful support families
ALLOWED_SEVERITIES = {"high", "medium", "low"}
ALLOWED_CATEGORIES = {"Trojan", "Other Malware"}
TROJAN_LIKE_TAGS = {
    "Rbot!gen",
    "Yuner.A",
    "Agent.FYI",
    "Autorun.K",
    "Alueron.gen!J",
    "Malex.gen!J",
    "C2LOP.P",
    "C2LOP.gen!g",
    "Dialplatform.B",
}

# ---- Family mapping table (loaded from JSON) ----
with open("/home/kali/Desktop/FYP1/MalwareImageRecognitionFYP1/models/family_mapping.json", "r") as f:
    _raw_map = json.load(f)

FAMILY_MAP = {}
for family, info in _raw_map.items():
    category = info.get("category")
    severity = str(info.get("severity", "")).lower()

    if category not in ALLOWED_CATEGORIES:
        raise RuntimeError(
            f"Invalid category '{category}' for family '{family}'. "
            f"Allowed: {sorted(ALLOWED_CATEGORIES)}"
        )
    if severity not in ALLOWED_SEVERITIES:
        raise RuntimeError(
            f"Invalid severity '{severity}' for family '{family}'. "
            f"Allowed: {sorted(ALLOWED_SEVERITIES)}"
        )

    FAMILY_MAP[family] = (category, severity)

# ---- Load labels ----
with open("/home/kali/Desktop/FYP1/MalwareImageRecognitionFYP1/models/labels.json", "r") as f:
    labels = json.load(f)
idx_to_label = {v: k for k, v in labels.items()}
num_classes = len(labels)

missing_families = sorted(set(labels.keys()) - set(FAMILY_MAP.keys()))
if missing_families:
    raise RuntimeError(
        "family_mapping.json is missing mapped families: " + ", ".join(missing_families)
    )

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
        CONFIDENCE_THRESHOLD = 0.70  # 70% confidence required
        
        confidence_pct = f"{confidence * 100:.1f}%"
        
        detected_type = idx_to_label[pred_idx]

        if confidence < CONFIDENCE_THRESHOLD:
            # Low confidence — preserve mapped severity when available, but mark as possible
            if detected_type in FAMILY_MAP:
                mapped_category, mapped_severity = FAMILY_MAP[detected_type]
                trojan_type = "Possible " + mapped_category
                severity = mapped_severity
            else:
                trojan_type = "Uncertain"
                severity = "unknown"
            trojan_subtype = detected_type
        else:
            if detected_type not in TROJAN_LIKE_TAGS and confidence > 0.80:
                trojan_type = "Possible Trojan Variant"
                severity = "medium"
            # Look up family in mapping table (
            elif detected_type in FAMILY_MAP:
                trojan_type, severity = FAMILY_MAP[detected_type]
            else:
                trojan_type = "Other Malware"
                severity = "medium"
            trojan_subtype = detected_type  

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