#!/usr/bin/env python3
"""
Convert binary files (executables, DLLs, etc.) to visual representations
for malware analysis using CNN models.
"""

import numpy as np
from PIL import Image
import sys
import os
import math

def file_to_image(file_path, output_path=None, width=224):
    """
    Convert a binary file to a grayscale image representation.
    
    Args:
        file_path: Path to the binary file
        output_path: Where to save the image (optional)
        width: Width of output image (default 224 for CNN compatibility)
    
    Returns:
        Path to the generated image
    """
    # Read binary file
    with open(file_path, 'rb') as f:
        file_bytes = f.read()
    
    if len(file_bytes) == 0:
        raise ValueError("File is empty")
    
    # Convert bytes to numpy array (0-255 values)
    byte_array = np.frombuffer(file_bytes, dtype=np.uint8)
    
    # Calculate height to maintain aspect ratio
    height = math.ceil(len(byte_array) / width)
    
    # Pad with zeros if necessary to fill the image
    total_pixels = width * height
    if len(byte_array) < total_pixels:
        byte_array = np.pad(byte_array, (0, total_pixels - len(byte_array)), 
                           mode='constant', constant_values=0)
    
    # Reshape to 2D image
    img_array = byte_array.reshape(height, width)
    
    # Create grayscale image
    img = Image.fromarray(img_array, mode='L')
    
    # Resize to 224x224 for CNN input
    img = img.resize((224, 224), Image.Resampling.LANCZOS)
    
    # Convert to RGB for model compatibility
    img_rgb = Image.new('RGB', (224, 224))
    img_rgb.paste(img)
    
    # Save image
    if output_path is None:
        base_name = os.path.basename(file_path)
        output_path = f"{base_name}_visualization.png"
    
    img_rgb.save(output_path)
    print(f"✓ Converted '{file_path}' to '{output_path}'")
    print(f"  File size: {len(file_bytes)} bytes")
    print(f"  Image size: 224x224 RGB")
    
    return output_path

def main():
    if len(sys.argv) < 2:
        print("Usage: python3 convert_file_to_image.py <file_path> [output_path]")
        print("Example: python3 convert_file_to_image.py malware.exe malware_viz.png")
        sys.exit(1)
    
    file_path = sys.argv[1]
    output_path = sys.argv[2] if len(sys.argv) > 2 else None
    
    if not os.path.exists(file_path):
        print(f"Error: File '{file_path}' not found")
        sys.exit(1)
    
    try:
        result = file_to_image(file_path, output_path)
        print(f"\n→ Now you can upload '{result}' to the malware detection system!")
    except Exception as e:
        print(f"Error: {e}")
        sys.exit(1)

if __name__ == "__main__":
    main()
