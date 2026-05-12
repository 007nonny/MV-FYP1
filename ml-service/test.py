#!/usr/bin/env python3
"""
Test the trained malware classification model
"""
import torch
import torch.nn as nn
from torchvision import models
from PIL import Image
import json
import os
import glob

from transforms_config import get_eval_transform

# Configuration
MODEL_PATH = "/home/kali/Desktop/FYP1/MalwareImageRecognitionFYP1/models/model.pt"
LABELS_PATH = "/home/kali/Desktop/FYP1/MalwareImageRecognitionFYP1/models/labels.json"
TEST_SAMPLES_DIR = "/home/kali/Desktop/FYP1/MalwareImageRecognitionFYP1/Dataset-1/malimg_paper_dataset_imgs"
IMG_SIZE = 224

# Load labels
with open(LABELS_PATH, 'r') as f:
    labels_dict = json.load(f)
    
# Reverse mapping: index -> class name
idx_to_label = {v: k for k, v in labels_dict.items()}
num_classes = len(labels_dict)

print(f"Loaded {num_classes} malware classes")

# Image preprocessing
test_transform = get_eval_transform(IMG_SIZE)

# Load model
device = torch.device("cuda" if torch.cuda.is_available() else "cpu")
print(f"Using device: {device}")

model = models.mobilenet_v2()
model.classifier[1] = nn.Linear(model.classifier[1].in_features, num_classes)
model.load_state_dict(torch.load(MODEL_PATH, map_location=device))
model = model.to(device)
model.eval()

print("\nModel loaded successfully!")

# Test with random samples from different classes
print("\n" + "="*70)
print("Testing model with sample images:")
print("="*70 + "\n")

# Get random sample from each of 5 classes
test_classes = ['Adialer.C', 'Agent.FYI', 'Allaple.A', 'Rbot!gen', 'VB.AT']
correct = 0
total = 0

for true_class in test_classes:
    class_dir = os.path.join(TEST_SAMPLES_DIR, true_class)
    if not os.path.exists(class_dir):
        continue
    
    # Get first image from this class
    images = glob.glob(os.path.join(class_dir, "*.png"))
    if not images:
        continue
    
    img_path = images[0]
    
    # Load and preprocess image
    img = Image.open(img_path)
    img_tensor = test_transform(img).unsqueeze(0).to(device)
    
    # Predict
    with torch.no_grad():
        outputs = model(img_tensor)
        probabilities = torch.nn.functional.softmax(outputs, dim=1)
        confidence, predicted_idx = torch.max(probabilities, 1)
        predicted_class = idx_to_label[predicted_idx.item()]
        confidence_pct = confidence.item() * 100
    
    # Check if correct
    is_correct = predicted_class == true_class
    correct += int(is_correct)
    total += 1
    
    status = "✓ CORRECT" if is_correct else "✗ WRONG"
    
    print(f"{status}")
    print(f"  True Class:      {true_class}")
    print(f"  Predicted:       {predicted_class}")
    print(f"  Confidence:      {confidence_pct:.2f}%")
    print(f"  Sample:          {os.path.basename(img_path)}")
    print()

print("="*70)
print(f"Test Accuracy: {correct}/{total} = {(correct/total)*100:.2f}%")
print("="*70)

# Show top-3 predictions for one sample
print("\n" + "="*70)
print("Detailed prediction example (Top-3 classes):")
print("="*70 + "\n")

sample_class = 'Allaple.A'
class_dir = os.path.join(TEST_SAMPLES_DIR, sample_class)
images = glob.glob(os.path.join(class_dir, "*.png"))
if images:
    img_path = images[0]
    img = Image.open(img_path)
    img_tensor = test_transform(img).unsqueeze(0).to(device)
    
    with torch.no_grad():
        outputs = model(img_tensor)
        probabilities = torch.nn.functional.softmax(outputs, dim=1)
        top3_prob, top3_idx = torch.topk(probabilities, 3)
        
        print(f"Sample: {os.path.basename(img_path)}")
        print(f"True class: {sample_class}\n")
        print("Top 3 predictions:")
        for i in range(3):
            pred_class = idx_to_label[top3_idx[0][i].item()]
            pred_conf = top3_prob[0][i].item() * 100
            print(f"  {i+1}. {pred_class:20s} - {pred_conf:6.2f}%")

print("\n✅ Testing complete!")
