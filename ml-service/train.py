import torch
import torch.nn as nn
import torch.optim as optim
from torch.utils.data import DataLoader
from torchvision import datasets, transforms, models
import json
import os
from tqdm import tqdm

# ---- Configuration ----
DATA_DIR = "/home/kali/Desktop/FYP1/MalwareImageRecognitionFYP1/Dataset-1/malimg_paper_dataset_imgs"  # Your dataset folder
TEST_DIR = "/home/kali/Desktop/FYP1/MalwareImageRecognitionFYP1/Dataset-1/test"   # Optional test folder
MODEL_SAVE_PATH = "/home/kali/Desktop/FYP1/MalwareImageRecognitionFYP1/models/model.pt"
LABELS_SAVE_PATH = "/home/kali/Desktop/FYP1/MalwareImageRecognitionFYP1/models/labels.json"
BATCH_SIZE = 32
EPOCHS = 10
LEARNING_RATE = 0.001
IMG_SIZE = 224

# ---- Data transforms ----
train_transform = transforms.Compose([
    transforms.Resize((IMG_SIZE, IMG_SIZE)),
    transforms.Grayscale(num_output_channels=3),  # Convert grayscale to 3-channel RGB
    transforms.RandomHorizontalFlip(),
    transforms.RandomRotation(10),
    transforms.ToTensor(),
    transforms.Normalize([0.485, 0.456, 0.406], [0.229, 0.224, 0.225])
])

test_transform = transforms.Compose([
    transforms.Resize((IMG_SIZE, IMG_SIZE)),
    transforms.Grayscale(num_output_channels=3),  # Convert grayscale to 3-channel RGB
    transforms.ToTensor(),
    transforms.Normalize([0.485, 0.456, 0.406], [0.229, 0.224, 0.225])
])

# ---- Load dataset ----
print("Loading dataset...")
train_dataset = datasets.ImageFolder(DATA_DIR, transform=train_transform)
train_loader = DataLoader(train_dataset, batch_size=BATCH_SIZE, shuffle=True)

# Load test set if exists
if os.path.exists(TEST_DIR):
    test_dataset = datasets.ImageFolder(TEST_DIR, transform=test_transform)
    test_loader = DataLoader(test_dataset, batch_size=BATCH_SIZE, shuffle=False)
    has_test = True
else:
    has_test = False
    print("No test set found, training only")

# ---- Save labels ----
labels = {class_name: idx for class_name, idx in train_dataset.class_to_idx.items()}
os.makedirs(os.path.dirname(LABELS_SAVE_PATH), exist_ok=True)
with open(LABELS_SAVE_PATH, "w") as f:
    json.dump(labels, f, indent=2)
print(f"Labels saved: {labels}")

num_classes = len(labels)

# ---- Build model ----
device = torch.device("cuda" if torch.cuda.is_available() else "cpu")
print(f"Using device: {device}")

model = models.mobilenet_v2(weights='DEFAULT')  # Use pretrained weights
model.classifier[1] = nn.Linear(model.classifier[1].in_features, num_classes)
model = model.to(device)

# ---- Training setup ----
criterion = nn.CrossEntropyLoss()
optimizer = optim.Adam(model.parameters(), lr=LEARNING_RATE)

# ---- Training loop ----
print(f"\nStarting training for {EPOCHS} epochs...")
for epoch in range(EPOCHS):
    model.train()
    running_loss = 0.0
    correct = 0
    total = 0
    
    progress_bar = tqdm(train_loader, desc=f"Epoch {epoch+1}/{EPOCHS}")
    for images, labels_batch in progress_bar:
        images, labels_batch = images.to(device), labels_batch.to(device)
        
        # Forward pass
        optimizer.zero_grad()
        outputs = model(images)
        loss = criterion(outputs, labels_batch)
        
        # Backward pass
        loss.backward()
        optimizer.step()
        
        # Statistics
        running_loss += loss.item()
        _, predicted = outputs.max(1)
        total += labels_batch.size(0)
        correct += predicted.eq(labels_batch).sum().item()
        
        progress_bar.set_postfix({
            'loss': f'{running_loss/len(progress_bar):.3f}',
            'acc': f'{100.*correct/total:.2f}%'
        })
    
    epoch_loss = running_loss / len(train_loader)
    epoch_acc = 100. * correct / total
    print(f"Epoch {epoch+1}: Loss={epoch_loss:.4f}, Accuracy={epoch_acc:.2f}%")
    
    # Test evaluation
    if has_test:
        model.eval()
        test_correct = 0
        test_total = 0
        with torch.no_grad():
            for images, labels_batch in test_loader:
                images, labels_batch = images.to(device), labels_batch.to(device)
                outputs = model(images)
                _, predicted = outputs.max(1)
                test_total += labels_batch.size(0)
                test_correct += predicted.eq(labels_batch).sum().item()
        
        test_acc = 100. * test_correct / test_total
        print(f"Test Accuracy: {test_acc:.2f}%")

# ---- Save model ----
print(f"\nSaving model to {MODEL_SAVE_PATH}...")
torch.save(model.state_dict(), MODEL_SAVE_PATH)
print("Training complete!")
