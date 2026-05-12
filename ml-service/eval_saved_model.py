import json
from pathlib import Path

import torch
import torch.nn as nn
from sklearn.metrics import accuracy_score, precision_recall_fscore_support
from torch.utils.data import DataLoader
from torchvision import datasets, models

from transforms_config import get_eval_transform

ROOT = Path('/home/kali/Desktop/FYP1/MalwareImageRecognitionFYP1')
MODEL_PATH = ROOT / 'models' / 'model.pt'
LABELS_PATH = ROOT / 'models' / 'labels.json'
DATASETS = {
    'train': ROOT / 'Dataset-1' / 'train',
    'validation': ROOT / 'Dataset-1' / 'val',
    'test': ROOT / 'Dataset-1' / 'test',
}
OUTPUT_PATH = ROOT / 'models' / 'training_metrics.json'

with LABELS_PATH.open() as f:
    labels = json.load(f)

transform = get_eval_transform(224)

device = torch.device('cpu')
model = models.mobilenet_v2(weights=None)
model.classifier[1] = nn.Linear(model.classifier[1].in_features, len(labels))
model.load_state_dict(torch.load(MODEL_PATH, map_location=device))
model.eval()
criterion = nn.CrossEntropyLoss()
results = {}

for split_name, split_dir in DATASETS.items():
    dataset = datasets.ImageFolder(split_dir, transform=transform)
    loader = DataLoader(dataset, batch_size=32, shuffle=False, num_workers=0)
    running_loss = 0.0
    y_true, y_pred = [], []
    with torch.no_grad():
        for images, labels_batch in loader:
            outputs = model(images)
            loss = criterion(outputs, labels_batch)
            running_loss += loss.item()
            preds = outputs.argmax(dim=1)
            y_true.extend(labels_batch.tolist())
            y_pred.extend(preds.tolist())
    acc = accuracy_score(y_true, y_pred)
    macro_p, macro_r, macro_f1, _ = precision_recall_fscore_support(y_true, y_pred, average='macro', zero_division=0)
    weighted_p, weighted_r, weighted_f1, _ = precision_recall_fscore_support(y_true, y_pred, average='weighted', zero_division=0)
    results[split_name] = {
        'samples': len(dataset),
        'loss': running_loss / max(1, len(loader)),
        'accuracy': acc,
        'macro_precision': macro_p,
        'macro_recall': macro_r,
        'macro_f1': macro_f1,
        'weighted_precision': weighted_p,
        'weighted_recall': weighted_r,
        'weighted_f1': weighted_f1,
    }

with OUTPUT_PATH.open('w') as f:
    json.dump(results, f, indent=2)

print(json.dumps(results, indent=2))
print(f'\nSaved to {OUTPUT_PATH}')
