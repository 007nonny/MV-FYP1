#!/usr/bin/env python3
"""
Split the Malimg dataset into train/validation/test sets.
Creates stratified splits to ensure each class is represented in all sets.

Split ratio: 70% train, 15% validation, 15% test
"""

import os
import shutil
import random
from pathlib import Path
from collections import defaultdict

# Configuration
DATA_DIR = "/home/kali/Desktop/FYP1/MalwareImageRecognitionFYP1/Dataset-1/malimg_paper_dataset_imgs"
OUTPUT_DIR = "/home/kali/Desktop/FYP1/MalwareImageRecognitionFYP1/Dataset-1"
TRAIN_DIR = os.path.join(OUTPUT_DIR, "train")
VAL_DIR = os.path.join(OUTPUT_DIR, "val")
TEST_DIR = os.path.join(OUTPUT_DIR, "test")

TRAIN_SPLIT = 0.70
VAL_SPLIT = 0.15
TEST_SPLIT = 0.15

RANDOM_SEED = 42

def main():
    random.seed(RANDOM_SEED)
    
    print("="*70)
    print("Malimg Dataset Stratified Split (70% train / 15% val / 15% test)")
    print("="*70)
    
    # Get all families
    families = sorted([d for d in os.listdir(DATA_DIR) 
                       if os.path.isdir(os.path.join(DATA_DIR, d)) and d != ".git"])
    
    print(f"\nFound {len(families)} malware families")
    print(f"Seed: {RANDOM_SEED}\n")
    
    # Collect all images per family
    family_images = defaultdict(list)
    total_images = 0
    
    for family in families:
        family_path = os.path.join(DATA_DIR, family)
        images = [f for f in os.listdir(family_path) if f.endswith('.png')]
        family_images[family] = images
        total_images += len(images)
    
    print(f"Total images: {total_images}\n")
    print("Family counts:")
    print("-" * 70)
    for family in families:
        print(f"  {family:20s} : {len(family_images[family]):5d} images")
    print("-" * 70)
    
    # Clean up old splits if they exist
    for split_dir in [TRAIN_DIR, VAL_DIR, TEST_DIR]:
        if os.path.exists(split_dir):
            print(f"Removing existing {split_dir}...")
            shutil.rmtree(split_dir)
    
    # Create directory structure and split data
    train_count = 0
    val_count = 0
    test_count = 0
    
    print("\nPerforming stratified split...")
    
    for family in families:
        images = family_images[family]
        random.shuffle(images)
        
        # Calculate split indices
        train_end = int(len(images) * TRAIN_SPLIT)
        val_end = train_end + int(len(images) * VAL_SPLIT)
        
        train_imgs = images[:train_end]
        val_imgs = images[train_end:val_end]
        test_imgs = images[val_end:]
        
        # Create family subdirs in each split
        for split_dir in [TRAIN_DIR, VAL_DIR, TEST_DIR]:
            family_split_dir = os.path.join(split_dir, family)
            os.makedirs(family_split_dir, exist_ok=True)
        
        # Copy images to respective splits
        for img in train_imgs:
            src = os.path.join(DATA_DIR, family, img)
            dst = os.path.join(TRAIN_DIR, family, img)
            shutil.copy2(src, dst)
            train_count += 1
        
        for img in val_imgs:
            src = os.path.join(DATA_DIR, family, img)
            dst = os.path.join(VAL_DIR, family, img)
            shutil.copy2(src, dst)
            val_count += 1
        
        for img in test_imgs:
            src = os.path.join(DATA_DIR, family, img)
            dst = os.path.join(TEST_DIR, family, img)
            shutil.copy2(src, dst)
            test_count += 1
    
    # Print summary
    print("\n" + "="*70)
    print("Split Complete!")
    print("="*70)
    print(f"Train set: {train_count:5d} images ({train_count/total_images*100:.1f}%)")
    print(f"Val set:   {val_count:5d} images ({val_count/total_images*100:.1f}%)")
    print(f"Test set:  {test_count:5d} images ({test_count/total_images*100:.1f}%)")
    print(f"Total:     {train_count + val_count + test_count:5d} images")
    print("="*70)
    print(f"\nTrain dir: {TRAIN_DIR}")
    print(f"Val dir:   {VAL_DIR}")
    print(f"Test dir:  {TEST_DIR}")

if __name__ == "__main__":
    main()
