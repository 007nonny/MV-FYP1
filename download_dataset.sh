#!/bin/bash
# Script to download MalwareVision dataset with retry logic

MAX_RETRIES=5
RETRY_COUNT=0

cd /home/kali/Desktop/FYP1/MalwareImageRecognitionFYP1

while [ $RETRY_COUNT -lt $MAX_RETRIES ]; do
    echo "Download attempt $((RETRY_COUNT + 1))/$MAX_RETRIES..."
    
    source ml-service/.venv/bin/activate
    kaggle datasets download -d mohitchauhan04/malwarevision -p . --unzip
    
    if [ $? -eq 0 ]; then
        echo "Download successful!"
        exit 0
    else
        echo "Download failed, retrying in 10 seconds..."
        RETRY_COUNT=$((RETRY_COUNT + 1))
        sleep 10
        rm -f malwarevision.zip
    fi
done

echo "Failed after $MAX_RETRIES attempts"
exit 1
