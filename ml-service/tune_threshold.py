import json
from pathlib import Path

import torch

ROOT = Path('/home/kali/Desktop/FYP1/MalwareImageRecognitionFYP1')
VALIDATION_LOGITS_PATH = ROOT / 'models' / 'validation_logits.json'
CALIBRATION_PATH = ROOT / 'models' / 'calibration.json'
OUTPUT_PATH = ROOT / 'models' / 'decision_policy.json'

DEFAULT_THRESHOLD = 0.70
TARGET_ACCEPTED_ACCURACY = 0.99
THRESHOLDS = [round(x / 100, 2) for x in range(50, 100)]

with VALIDATION_LOGITS_PATH.open() as f:
    payload = json.load(f)
records = payload['records']
if not records:
    raise RuntimeError('validation_logits.json contains no records')

temperature = 1.0
if CALIBRATION_PATH.exists():
    with CALIBRATION_PATH.open() as f:
        calibration = json.load(f)
    temperature = float(calibration.get('temperature', 1.0))
    if temperature <= 0:
        raise RuntimeError(f'Invalid calibration temperature: {temperature}')

logits = torch.tensor([record['logits'] for record in records], dtype=torch.float32)
labels = torch.tensor([record['true_idx'] for record in records], dtype=torch.long)
probabilities = torch.softmax(logits / temperature, dim=1)
confidences, predictions = probabilities.max(dim=1)
correct = predictions.eq(labels)

def evaluate_threshold(threshold: float) -> dict:
    accepted = confidences >= threshold
    accepted_count = int(accepted.sum().item())
    total = int(labels.numel())
    coverage = accepted_count / total if total else 0.0
    rejected_count = total - accepted_count
    rejection_rate = rejected_count / total if total else 0.0

    if accepted_count:
        accepted_accuracy = float(correct[accepted].float().mean().item())
        incorrect_accepted = int((~correct[accepted]).sum().item())
    else:
        accepted_accuracy = 0.0
        incorrect_accepted = 0

    return {
        'threshold': threshold,
        'coverage': coverage,
        'accepted_count': accepted_count,
        'rejected_count': rejected_count,
        'rejection_rate': rejection_rate,
        'accepted_accuracy': accepted_accuracy,
        'incorrect_accepted': incorrect_accepted,
    }

results = [evaluate_threshold(t) for t in THRESHOLDS]
current_result = evaluate_threshold(DEFAULT_THRESHOLD)

meeting_target = [r for r in results if r['accepted_accuracy'] >= TARGET_ACCEPTED_ACCURACY and r['accepted_count'] > 0]
if meeting_target:
    selected = max(meeting_target, key=lambda r: (r['coverage'], r['accepted_accuracy'], -r['threshold']))
    selection_reason = f'max coverage with accepted_accuracy >= {TARGET_ACCEPTED_ACCURACY:.2f}'
else:
    selected = max(results, key=lambda r: (r['accepted_accuracy'] * r['coverage'], r['accepted_accuracy'], r['coverage']))
    selection_reason = 'best accepted_accuracy * coverage fallback'

summary = {
    'method': 'validation_threshold_tuning',
    'source_logits': str(VALIDATION_LOGITS_PATH),
    'temperature_used': temperature,
    'total_validation_samples': int(labels.numel()),
    'selection_reason': selection_reason,
    'target_accepted_accuracy': TARGET_ACCEPTED_ACCURACY,
    'default_threshold': DEFAULT_THRESHOLD,
    'default_threshold_metrics': current_result,
    'selected_threshold': selected['threshold'],
    'selected_threshold_metrics': selected,
    'top_candidates': sorted(results, key=lambda r: (r['accepted_accuracy'] >= TARGET_ACCEPTED_ACCURACY, r['coverage'], r['accepted_accuracy']), reverse=True)[:10],
}

with OUTPUT_PATH.open('w') as f:
    json.dump(summary, f, indent=2)

print(json.dumps(summary, indent=2))
print(f'\nSaved decision policy to {OUTPUT_PATH}')
