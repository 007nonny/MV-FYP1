import os
import numpy as np
from PIL import Image
import random

# Create dataset structure for Trojan types + Benign
trojan_types = [
    'TrojanDownloader',
    'TrojanDropper', 
    'TrojanSpy',
    'TrojanBanker',
    'Benign'
]

base_dir = '/home/kali/Desktop/FYP1/MalwareImageRecognitionFYP1/dataset'
num_train_images = 50  # per class (reduced from 100)
num_test_images = 10    # per class (reduced from 20)

def generate_trojan_pattern_image(trojan_type, size=224):
    """Generate a unique pattern for each trojan type"""
    img = np.zeros((size, size, 3), dtype=np.uint8)
    
    # Different visual patterns for each trojan type
    if trojan_type == 'TrojanDownloader':
        # Vertical stripes pattern
        for i in range(0, size, 10):
            img[:, i:i+5] = [random.randint(100, 255), random.randint(0, 100), random.randint(0, 50)]
    
    elif trojan_type == 'TrojanDropper':
        # Diagonal pattern
        for i in range(size):
            for j in range(size):
                if (i + j) % 20 < 10:
                    img[i, j] = [random.randint(0, 100), random.randint(100, 255), random.randint(0, 50)]
    
    elif trojan_type == 'TrojanSpy':
        # Circular pattern
        center = size // 2
        for i in range(size):
            for j in range(size):
                dist = np.sqrt((i - center)**2 + (j - center)**2)
                if int(dist) % 20 < 10:
                    img[i, j] = [random.randint(0, 50), random.randint(0, 100), random.randint(100, 255)]
    
    elif trojan_type == 'TrojanBanker':
        # Grid pattern
        for i in range(0, size, 15):
            img[i:i+3, :] = [random.randint(100, 255), random.randint(100, 255), random.randint(0, 50)]
            img[:, i:i+3] = [random.randint(100, 255), random.randint(100, 255), random.randint(0, 50)]
    
    else:  # Benign
        # Smooth gradient pattern (looks more "normal")
        for i in range(size):
            for j in range(size):
                val = int((i + j) / (2 * size) * 255)
                noise_val = random.randint(-20, 20)
                img[i, j] = [
                    max(0, min(255, val)),
                    max(0, min(255, val)),
                    max(0, min(255, val + noise_val))
                ]
    
    # Add some random noise
    noise = np.random.randint(0, 30, (size, size, 3), dtype=np.uint8)
    img = np.clip(img.astype(np.int16) + noise.astype(np.int16), 0, 255).astype(np.uint8)
    
    return Image.fromarray(img)

print("Generating sample Trojan dataset...")

for split, num_images in [('train', num_train_images), ('test', num_test_images)]:
    for trojan_type in trojan_types:
        dir_path = os.path.join(base_dir, split, trojan_type)
        os.makedirs(dir_path, exist_ok=True)
        
        print(f"Creating {num_images} {split} images for {trojan_type}...")
        
        for i in range(num_images):
            img = generate_trojan_pattern_image(trojan_type)
            img.save(os.path.join(dir_path, f'{trojan_type}_{i:04d}.png'))

print("\n✓ Dataset generated successfully!")
print(f"\nStructure:")
print(f"  dataset/")
print(f"    train/")
for t in trojan_types:
    print(f"      {t}/ ({num_train_images} images)")
print(f"    test/")
for t in trojan_types:
    print(f"      {t}/ ({num_test_images} images)")
print(f"\nTotal: {len(trojan_types) * (num_train_images + num_test_images)} images")
