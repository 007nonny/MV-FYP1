import json
from pathlib import Path

import torch
import torch.nn.functional as F

ROOT = Path('/home/kali/Desktop/FYP1/MalwareImageRecognitionFYP1')
VALIDATION_LOGITS_PATH = ROOT / 'models' / 'validation_logits.json'
OUTPUT_PATH = ROOT / 'models' / 'calibration.json'


def expected_calibration_error(probabilities: torch.Tensor, labels: torch.Tensor, n_bins: int = 15) -> float:
    confidences, predictions = probabilities.max(dim=1)
    accuracies = predictions.eq(labels)
    bin_boundaries = torch.linspace(0.0, 1.0, steps=n_bins + 1)
    ece = torch.zeros(1, dtype=torch.float32)

    for bin_lower, bin_upper in zip(bin_boundaries[:-1], bin_boundaries[1:]):
        in_bin = (confidences > bin_lower) & (confidences <= bin_upper)
        proportion_in_bin = in_bin.float().mean()
        if proportion_in_bin.item() > 0:
            accuracy_in_bin = accuracies[in_bin].float().mean()
            avg_confidence_in_bin = confidences[in_bin].mean()
            ece += torch.abs(avg_confidence_in_bin - accuracy_in_bin) * proportion_in_bin

    return float(ece.item())


def brier_score(probabilities: torch.Tensor, labels: torch.Tensor, num_classes: int) -> float:
    one_hot = F.one_hot(labels, num_classes=num_classes).float()
    return float(torch.mean(torch.sum((probabilities - one_hot) ** 2, dim=1)).item())


with VALIDATION_LOGITS_PATH.open() as f:
    payload = json.load(f)

records = payload['records']
if not records:
    raise RuntimeError('validation_logits.json contains no records')

logits = torch.tensor([record['logits'] for record in records], dtype=torch.float32)
labels = torch.tensor([record['true_idx'] for record in records], dtype=torch.long)
num_classes = int(logits.shape[1])

baseline_probs = torch.softmax(logits, dim=1)
baseline_nll = float(F.cross_entropy(logits, labels).item())
baseline_ece = expected_calibration_error(baseline_probs, labels)
baseline_brier = brier_score(baseline_probs, labels, num_classes)
baseline_accuracy = float((baseline_probs.argmax(dim=1) == labels).float().mean().item())

log_temperature = torch.nn.Parameter(torch.zeros(1, dtype=torch.float32))
optimizer = torch.optim.LBFGS([log_temperature], lr=0.1, max_iter=100, line_search_fn='strong_wolfe')


def closure():
    optimizer.zero_grad()
    temperature = torch.exp(log_temperature)
    loss = F.cross_entropy(logits / temperature, labels)
    loss.backward()
    return loss

optimizer.step(closure)
calibrated_temperature = float(torch.exp(log_temperature).item())

calibrated_probs = torch.softmax(logits / calibrated_temperature, dim=1)
calibrated_nll = float(F.cross_entropy(logits / calibrated_temperature, labels).item())
calibrated_ece = expected_calibration_error(calibrated_probs, labels)
calibrated_brier = brier_score(calibrated_probs, labels, num_classes)
calibrated_accuracy = float((calibrated_probs.argmax(dim=1) == labels).float().mean().item())

result = {
    'method': 'temperature_scaling',
    'temperature': calibrated_temperature,
    'validation_samples': len(records),
    'metrics_before': {
        'nll': baseline_nll,
        'ece': baseline_ece,
        'brier_score': baseline_brier,
        'accuracy': baseline_accuracy,
    },
    'metrics_after': {
        'nll': calibrated_nll,
        'ece': calibrated_ece,
        'brier_score': calibrated_brier,
        'accuracy': calibrated_accuracy,
    },
    'source_logits': str(VALIDATION_LOGITS_PATH),
}

with OUTPUT_PATH.open('w') as f:
    json.dump(result, f, indent=2)

print(json.dumps(result, indent=2))
print(f'\nSaved calibration to {OUTPUT_PATH}')
