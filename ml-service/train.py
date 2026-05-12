import copy
import json
import os
import random

import numpy as np
import torch
import torch.nn as nn
import torch.optim as optim
from sklearn.metrics import accuracy_score, classification_report, precision_recall_fscore_support
from sklearn.model_selection import train_test_split
from torch.utils.data import DataLoader, Dataset
from torchvision import datasets, models
from tqdm import tqdm

from transforms_config import get_eval_transform, get_train_transform


# ---- Configuration ----
DATA_DIR = "/home/kali/Desktop/FYP1/MalwareImageRecognitionFYP1/Dataset-1/malimg_paper_dataset_imgs"
MODEL_SAVE_PATH = "/home/kali/Desktop/FYP1/MalwareImageRecognitionFYP1/models/model.pt"
LABELS_SAVE_PATH = "/home/kali/Desktop/FYP1/MalwareImageRecognitionFYP1/models/labels.json"
METRICS_SAVE_PATH = "/home/kali/Desktop/FYP1/MalwareImageRecognitionFYP1/models/training_metrics.json"

BATCH_SIZE = 32
EPOCHS = 10
LEARNING_RATE = 0.001
IMG_SIZE = 224
RANDOM_SEED = 42

# Split ratios
TRAIN_RATIO = 0.70
VAL_RATIO = 0.15
TEST_RATIO = 0.15


def set_seed(seed: int) -> None:
    random.seed(seed)
    np.random.seed(seed)
    torch.manual_seed(seed)
    if torch.cuda.is_available():
        torch.cuda.manual_seed_all(seed)


class TransformSubset(Dataset):
    def __init__(self, base_dataset: datasets.ImageFolder, indices, transform=None):
        self.base_dataset = base_dataset
        self.indices = list(indices)
        self.transform = transform

    def __len__(self):
        return len(self.indices)

    def __getitem__(self, item):
        base_idx = self.indices[item]
        path, target = self.base_dataset.samples[base_idx]
        image = self.base_dataset.loader(path)
        if self.transform is not None:
            image = self.transform(image)
        return image, target


def evaluate(model, loader, criterion, device):
    model.eval()
    running_loss = 0.0
    y_true, y_pred = [], []

    with torch.no_grad():
        for images, labels_batch in loader:
            images, labels_batch = images.to(device), labels_batch.to(device)
            outputs = model(images)
            loss = criterion(outputs, labels_batch)
            running_loss += loss.item()

            preds = outputs.argmax(dim=1)
            y_true.extend(labels_batch.cpu().numpy().tolist())
            y_pred.extend(preds.cpu().numpy().tolist())

    avg_loss = running_loss / max(1, len(loader))
    acc = accuracy_score(y_true, y_pred)
    return avg_loss, acc, y_true, y_pred


def distribution(indices, targets, idx_to_label):
    counts = {}
    for i in indices:
        cls = idx_to_label[targets[i]]
        counts[cls] = counts.get(cls, 0) + 1
    return counts


def main():
    set_seed(RANDOM_SEED)

    assert abs((TRAIN_RATIO + VAL_RATIO + TEST_RATIO) - 1.0) < 1e-8, "Split ratios must sum to 1.0"

    # ---- Data transforms ----
    train_transform = get_train_transform(IMG_SIZE)
    eval_transform = get_eval_transform(IMG_SIZE)

    # ---- Load full dataset ----
    print("Loading dataset...")
    base_dataset = datasets.ImageFolder(DATA_DIR)
    labels = {class_name: idx for class_name, idx in base_dataset.class_to_idx.items()}
    idx_to_label = {v: k for k, v in labels.items()}
    num_classes = len(labels)

    targets = np.array(base_dataset.targets)
    all_indices = np.arange(len(base_dataset))

    # ---- Stratified split: train / (val+test) ----
    temp_ratio = VAL_RATIO + TEST_RATIO
    train_indices, temp_indices = train_test_split(
        all_indices,
        test_size=temp_ratio,
        random_state=RANDOM_SEED,
        stratify=targets,
    )

    # ---- Stratified split: val / test ----
    temp_targets = targets[temp_indices]
    val_portion_of_temp = VAL_RATIO / (VAL_RATIO + TEST_RATIO)

    val_indices, test_indices = train_test_split(
        temp_indices,
        test_size=(1.0 - val_portion_of_temp),
        random_state=RANDOM_SEED,
        stratify=temp_targets,
    )

    print(f"Total samples: {len(base_dataset)}")
    print(f"Train samples: {len(train_indices)}")
    print(f"Validation samples: {len(val_indices)}")
    print(f"Test samples: {len(test_indices)}")

    # ---- Create datasets and loaders ----
    train_dataset = TransformSubset(base_dataset, train_indices, transform=train_transform)
    val_dataset = TransformSubset(base_dataset, val_indices, transform=eval_transform)
    test_dataset = TransformSubset(base_dataset, test_indices, transform=eval_transform)

    train_loader = DataLoader(train_dataset, batch_size=BATCH_SIZE, shuffle=True, num_workers=2)
    val_loader = DataLoader(val_dataset, batch_size=BATCH_SIZE, shuffle=False, num_workers=2)
    test_loader = DataLoader(test_dataset, batch_size=BATCH_SIZE, shuffle=False, num_workers=2)

    # ---- Save labels ----
    os.makedirs(os.path.dirname(LABELS_SAVE_PATH), exist_ok=True)
    with open(LABELS_SAVE_PATH, "w") as f:
        json.dump(labels, f, indent=2)
    print(f"Labels saved to: {LABELS_SAVE_PATH}")

    # ---- Build model ----
    device = torch.device("cuda" if torch.cuda.is_available() else "cpu")
    print(f"Using device: {device}")

    model = models.mobilenet_v2(weights="DEFAULT")
    model.classifier[1] = nn.Linear(model.classifier[1].in_features, num_classes)
    model = model.to(device)

    # ---- Training setup ----
    criterion = nn.CrossEntropyLoss()
    optimizer = optim.Adam(model.parameters(), lr=LEARNING_RATE)

    best_val_acc = -1.0
    best_state_dict = copy.deepcopy(model.state_dict())
    history = []

    # ---- Training loop ----
    print(f"\nStarting training for {EPOCHS} epochs...")
    for epoch in range(EPOCHS):
        model.train()
        running_loss = 0.0
        running_correct = 0
        running_total = 0

        progress_bar = tqdm(train_loader, desc=f"Epoch {epoch + 1}/{EPOCHS}")
        for images, labels_batch in progress_bar:
            images, labels_batch = images.to(device), labels_batch.to(device)

            optimizer.zero_grad()
            outputs = model(images)
            loss = criterion(outputs, labels_batch)
            loss.backward()
            optimizer.step()

            running_loss += loss.item()
            preds = outputs.argmax(dim=1)
            running_total += labels_batch.size(0)
            running_correct += preds.eq(labels_batch).sum().item()

            progress_bar.set_postfix({
                "loss": f"{running_loss / max(1, len(progress_bar)):.3f}",
                "acc": f"{100.0 * running_correct / max(1, running_total):.2f}%",
            })

        train_loss = running_loss / max(1, len(train_loader))
        train_acc = running_correct / max(1, running_total)

        val_loss, val_acc, _, _ = evaluate(model, val_loader, criterion, device)

        history.append({
            "epoch": epoch + 1,
            "train_loss": train_loss,
            "train_acc": train_acc,
            "val_loss": val_loss,
            "val_acc": val_acc,
        })

        print(
            f"Epoch {epoch + 1}: "
            f"Train Loss={train_loss:.4f}, Train Acc={train_acc * 100:.2f}% | "
            f"Val Loss={val_loss:.4f}, Val Acc={val_acc * 100:.2f}%"
        )

        if val_acc > best_val_acc:
            best_val_acc = val_acc
            best_state_dict = copy.deepcopy(model.state_dict())

    # ---- Load best model (by validation accuracy) ----
    model.load_state_dict(best_state_dict)

    # ---- Final test evaluation ----
    test_loss, test_acc, y_true, y_pred = evaluate(model, test_loader, criterion, device)
    macro_p, macro_r, macro_f1, _ = precision_recall_fscore_support(
        y_true, y_pred, average="macro", zero_division=0
    )
    weighted_p, weighted_r, weighted_f1, _ = precision_recall_fscore_support(
        y_true, y_pred, average="weighted", zero_division=0
    )

    report = classification_report(
        y_true,
        y_pred,
        labels=list(range(num_classes)),
        target_names=[idx_to_label[i] for i in range(num_classes)],
        output_dict=True,
        zero_division=0,
    )

    print("\nFinal Test Metrics:")
    print(f"  Test Loss: {test_loss:.4f}")
    print(f"  Test Accuracy: {test_acc * 100:.2f}%")
    print(f"  Macro Precision: {macro_p:.4f}")
    print(f"  Macro Recall: {macro_r:.4f}")
    print(f"  Macro F1: {macro_f1:.4f}")
    print(f"  Weighted F1: {weighted_f1:.4f}")

    # ---- Save model ----
    print(f"\nSaving best model to {MODEL_SAVE_PATH}...")
    torch.save(model.state_dict(), MODEL_SAVE_PATH)

    # ---- Save metrics report ----
    metrics = {
        "config": {
            "batch_size": BATCH_SIZE,
            "epochs": EPOCHS,
            "learning_rate": LEARNING_RATE,
            "img_size": IMG_SIZE,
            "seed": RANDOM_SEED,
            "split": {
                "train": TRAIN_RATIO,
                "validation": VAL_RATIO,
                "test": TEST_RATIO,
            },
        },
        "counts": {
            "total": len(base_dataset),
            "train": len(train_indices),
            "validation": len(val_indices),
            "test": len(test_indices),
        },
        "class_distribution": {
            "train": distribution(train_indices, targets, idx_to_label),
            "validation": distribution(val_indices, targets, idx_to_label),
            "test": distribution(test_indices, targets, idx_to_label),
        },
        "best_validation_accuracy": best_val_acc,
        "test_metrics": {
            "loss": test_loss,
            "accuracy": test_acc,
            "macro_precision": macro_p,
            "macro_recall": macro_r,
            "macro_f1": macro_f1,
            "weighted_precision": weighted_p,
            "weighted_recall": weighted_r,
            "weighted_f1": weighted_f1,
        },
        "epoch_history": history,
        "classification_report": report,
    }

    with open(METRICS_SAVE_PATH, "w") as f:
        json.dump(metrics, f, indent=2)

    print(f"Metrics saved to: {METRICS_SAVE_PATH}")
    print("Training complete!")


if __name__ == "__main__":
    main()
