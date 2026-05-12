from fastapi import FastAPI, UploadFile, File
from fastapi.responses import JSONResponse
import torch
from torchvision import models
from PIL import Image
import json
import io
from pathlib import Path

from transforms_config import get_eval_transform

app = FastAPI()

ROOT = Path("/home/kali/Desktop/FYP1/MalwareImageRecognitionFYP1")
MODELS_DIR = ROOT / "models"
CALIBRATION_PATH = MODELS_DIR / "calibration.json"
DECISION_POLICY_PATH = MODELS_DIR / "decision_policy.json"

# ---- Severity policy ----
# High   = stronger persistence / wider damage / botnet or downloader behavior
# Medium = trojan families with moderate risk
# Low    = obfuscators or less directly harmful support families
ALLOWED_SEVERITIES = {"high", "medium", "low"}
ALLOWED_CATEGORIES = {"Trojan", "Other Malware"}
SEVERITY_MAPPING = {
    # LOW
    "Adialer.C": "Low",
    "Dialplatform.B": "Low",
    "Fakerean": "Low",
    "Swizzor.gen!E": "Low",
    "Swizzor.gen!I": "Low",
    "VB.AT": "Low",

    # MEDIUM
    "Dontovo.A": "Medium",
    "Instantaccess": "Medium",
    "Lolyda.AA1": "Medium",
    "Lolyda.AA2": "Medium",
    "Lolyda.AA3": "Medium",
    "Lolyda.AT": "Medium",
    "Obfuscator.AD": "Medium",
    "Rbot!gen": "Medium",
    "Skintrim.N": "Medium",
    "Yuner.A": "Medium",

    # HIGH
    "Agent.FYI": "High",
    "Allaple.A": "High",
    "Allaple.L": "High",
    "Alueron.gen!J": "High",
    "Autorun.K": "High",
    "C2LOP.gen!g": "High",
    "C2LOP.P": "High",
    "Malex.gen!J": "High",
    "Wintrim.BX": "High",
}
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
with open(MODELS_DIR / "family_mapping.json", "r") as f:
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
with open(MODELS_DIR / "labels.json", "r") as f:
    labels = json.load(f)
idx_to_label = {v: k for k, v in labels.items()}
num_classes = len(labels)

missing_families = sorted(set(labels.keys()) - set(FAMILY_MAP.keys()))
if missing_families:
    raise RuntimeError(
        "family_mapping.json is missing mapped families: " + ", ".join(missing_families)
    )

missing_severity_families = sorted(set(labels.keys()) - set(SEVERITY_MAPPING.keys()))
if missing_severity_families:
    raise RuntimeError(
        "Severity mapping is missing families: " + ", ".join(missing_severity_families)
    )

CALIBRATION = {"temperature": 1.0, "enabled": False}
if CALIBRATION_PATH.exists():
    with open(CALIBRATION_PATH, "r") as f:
        loaded_calibration = json.load(f)
    temperature = float(loaded_calibration.get("temperature", 1.0))
    if temperature <= 0:
        raise RuntimeError(f"Invalid temperature in {CALIBRATION_PATH}: {temperature}")
    CALIBRATION = {
        "temperature": temperature,
        "enabled": True,
    }

DECISION_POLICY = {"confidence_threshold": 0.70, "enabled": False}
if DECISION_POLICY_PATH.exists():
    with open(DECISION_POLICY_PATH, "r") as f:
        loaded_policy = json.load(f)
    confidence_threshold = float(loaded_policy.get("selected_threshold", 0.70))
    if not 0.0 <= confidence_threshold <= 1.0:
        raise RuntimeError(
            f"Invalid selected_threshold in {DECISION_POLICY_PATH}: {confidence_threshold}"
        )
    DECISION_POLICY = {
        "confidence_threshold": confidence_threshold,
        "enabled": True,
    }

# ---- Load model ----
device = torch.device("cpu")

model = models.mobilenet_v2(weights=None)
model.classifier[1] = torch.nn.Linear(
    model.classifier[1].in_features, num_classes
)
model.load_state_dict(torch.load(MODELS_DIR / "model.pt", map_location=device))
model.eval()

# ---- Image transform ----
transform = get_eval_transform(224)

@app.post("/analyze")
async def analyze(file: UploadFile = File(...)):
    try:
        image_bytes = await file.read()
        image = Image.open(io.BytesIO(image_bytes)).convert("RGB")
        image = transform(image).unsqueeze(0)

        with torch.no_grad():
            outputs = model(image)
            scaled_outputs = outputs / CALIBRATION["temperature"]
            probabilities = torch.nn.functional.softmax(scaled_outputs, dim=1)
            confidence, pred_idx = probabilities.max(1)
            confidence = confidence.item()
            pred_idx = pred_idx.item()

        # Confidence threshold for detection, tuned on validation when available
        CONFIDENCE_THRESHOLD = DECISION_POLICY["confidence_threshold"]
        
        confidence_pct = f"{confidence * 100:.1f}%"
        
        predicted_family = idx_to_label[pred_idx]
        severity = SEVERITY_MAPPING.get(predicted_family, "Unknown")

        if confidence < CONFIDENCE_THRESHOLD:
            # Low confidence — preserve category when available, but use family severity mapping
            if predicted_family in FAMILY_MAP:
                mapped_category, _ = FAMILY_MAP[predicted_family]
                trojan_type = "Possible " + mapped_category
            else:
                trojan_type = "Uncertain"
                severity = "Unknown"
            trojan_subtype = predicted_family
        else:
            if predicted_family not in TROJAN_LIKE_TAGS and confidence > 0.80:
                trojan_type = "Possible Trojan Variant"
            # Look up family in mapping table (
            elif predicted_family in FAMILY_MAP:
                trojan_type, _ = FAMILY_MAP[predicted_family]
            else:
                trojan_type = "Other Malware"
                severity = severity or "Unknown"
            trojan_subtype = predicted_family  

        return JSONResponse({
            "family": predicted_family,
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