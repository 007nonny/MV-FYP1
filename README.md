# Malware Image Recognition FYP

This project is an academic prototype for malware image recognition. It converts uploaded files into grayscale images and uses a MobileNetV2-based machine learning model to classify malware families and display severity, confidence, and classification results.

## Main Components
- php-webapp: Handles upload, conversion flow, result display, and database storage.
- ml-service: FastAPI service for image classification.
- models: Stores trained model files and mapping files.

## How to Run
1. Start the ML service.
2. Start the PHP web application.
3. Upload a file or malware image.
4. View the classification result.
