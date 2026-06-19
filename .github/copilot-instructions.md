# Copilot instructions for MalwareImageRecognitionFYP1

## Big picture architecture
- This repo is a 2-service malware analysis demo:
  - `php-webapp/` = UI, upload/conversion flow, history, and security controls.
  - `ml-service/` = FastAPI inference service (`POST /analyze`) using MobileNetV2.
- End-to-end flow is: binary upload -> Python conversion to image -> ML classification -> result page.
  - Conversion entry: `php-webapp/convert.php` calling `convert_file_to_image.py`.
  - Classification entry: `php-webapp/classify.php` calling `ML_ANALYZE_URL`.
  - Result rendering: `php-webapp/results.php`.
- Shared ML artifacts live in `models/` and are read by `ml-service/main.py`:
  - `model.pt`, `labels.json`, `family_mapping.json`, optional `calibration.json`, `decision_policy.json`.

## Critical runtime workflows
- Start full demo stack with `./start_demo.sh` (starts PHP on `:8000`, FastAPI on `:5000`).
- Stop demo services with `./stop_demo.sh`.
- Health checks:
  - Web app: `/health.php`
  - ML service: `/openapi.json`
- Primary ML dependency install is from `ml-service/requirements.txt` into `ml-service/.venv`.

## Model training/evaluation workflow (scripts in `ml-service/`)
- Dataset split script: `split_dataset.py` (70/15/15 stratified into `Dataset-1/train|val|test`).
- Training script: `train.py` writes `models/model.pt`, `models/labels.json`, `models/training_metrics.json`.
- Validation logits pipeline:
  1) `inspect_val_logits.py` -> `models/validation_logits.json`
  2) `calibrate_model.py` -> `models/calibration.json`
  3) `tune_threshold.py` -> `models/decision_policy.json`
- `main.py` consumes calibration temperature and selected threshold when files exist.

## Project-specific conventions to preserve
- Many Python scripts use hardcoded absolute project paths (`/home/kali/Desktop/FYP1/MalwareImageRecognitionFYP1/...`).
  - Keep path strategy consistent when editing; if refactoring to relative/env paths, update all dependent scripts together.
- `php-webapp/security.php` is the central place for security/session/rate-limit helpers; reuse it rather than duplicating logic.
- DB is optional at runtime:
  - If MySQL is unavailable, `classify.php` stores latest result in session and `results.php` renders from session fallback.
- Upload validation is MIME + extension based (`config.php` constants `ALLOWED_IMAGE_TYPES`, `ALLOWED_BINARY_TYPES`).

## Integration details that often break
- `classify.php` requires ML API response keys: `trojan_type`, `trojan_subtype`, `severity`, `confidence`.
- `results.php` expects confidence formatted like a percentage string (e.g., `96.4%`) or `N/A`.
- `main.py` validates category/severity values from `models/family_mapping.json`; invalid entries fail fast at startup.
- Keep `labels.json` and `family_mapping.json` in sync with model classes, or service startup will raise runtime errors.

## Editing guidance for agents
- Prefer minimal, local fixes; preserve current UX text and fallback behavior unless task explicitly asks otherwise.
- When changing inference policy, update both ML output semantics and PHP display assumptions (`classify.php`, `results.php`).
- When adding new malware families/classes, update all of: model labels, family mapping, severity mapping in `main.py`, and retrained artifacts in `models/`.
