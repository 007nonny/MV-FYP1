import json
from pathlib import Path

import torch
import torch.nn as nn
from torch.utils.data import DataLoader
from torchvision import datasets, models

from transforms_config import get_eval_transform

ROOT = Path('/home/kali/Desktop/FYP1/MalwareImageRecognitionFYP1')
MODEL_PATH = ROOT / 'models' / 'model.pt'
LABELS_PATH = ROOT / 'models' / 'labels.json'
VAL_DIR = ROOT / 'Dataset-1' / 'val'
OUTPUT_PATH = ROOT / 'models' / 'validation_logits.json'

with LABELS_PATH.open() as f:
    labels = json.load(f)

idx_to_label = {v: k for k, v in labels.items()}

transform = get_eval_transform(224)
dataset = datasets.ImageFolder(VAL_DIR, transform=transform)
loader = DataLoader(dataset, batch_size=32, shuffle=False, num_workers=0)

model = models.mobilenet_v2(weights=None)
model.classifier[1] = nn.Linear(model.classifier[1].in_features, len(labels))
model.load_state_dict(torch.load(MODEL_PATH, map_location='cpu'))
model.eval()

records = []
with torch.no_grad():
    sample_index = 0
    for images, targets in loader:
        outputs = model(images)
        probabilities = torch.softmax(outputs, dim=1)
        confidences, predictions = probabilities.max(dim=1)

        for i in range(images.size(0)):
            target_idx = int(targets[i].item())
            pred_idx = int(predictions[i].item())
            records.append({
                'sample_index': sample_index,
                'true_idx': target_idx,
                'true_family': idx_to_label[target_idx],
                'pred_idx': pred_idx,
                'pred_family': idx_to_label[pred_idx],
                'confidence': float(confidences[i].item()),
                'logits': [float(x) for x in outputs[i].tolist()],
            })
            sample_index += 1

correct = sum(1 for record in records if record['true_idx'] == record['pred_idx'])
summary = {
    'validation_dir': str(VAL_DIR),
    'samples': len(records),
    'classes': len(labels),
    'accuracy': correct / len(records) if records else 0.0,
    'output_path': str(OUTPUT_PATH),
    'first_3_records': records[:3],
}

with OUTPUT_PATH.open('w') as f:
    json.dump({
        'summary': summary,
        'records': records,
    }, f, indent=2)

print(json.dumps(summary, indent=2))
print(f'\nSaved validation logits to {OUTPUT_PATH}')
