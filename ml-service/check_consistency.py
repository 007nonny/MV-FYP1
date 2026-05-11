#!/usr/bin/env python3
"""Check consistency between dataset folders, label mappings, and family mappings."""

from __future__ import annotations

import json
from pathlib import Path

PROJECT_ROOT = Path('/home/kali/Desktop/FYP1/MalwareImageRecognitionFYP1')

DATASET_PATHS = {
    'dataset_root': PROJECT_ROOT / 'Dataset-1' / 'malimg_paper_dataset_imgs',
    'train': PROJECT_ROOT / 'Dataset-1' / 'train',
    'validation': PROJECT_ROOT / 'Dataset-1' / 'val',
    'test': PROJECT_ROOT / 'Dataset-1' / 'test',
}
LABELS_PATH = PROJECT_ROOT / 'models' / 'labels.json'
FAMILY_MAPPING_PATH = PROJECT_ROOT / 'models' / 'family_mapping.json'


def folder_classes(path: Path) -> list[str]:
    return sorted(item.name for item in path.iterdir() if item.is_dir())


def main() -> None:
    with LABELS_PATH.open() as f:
        labels = sorted(json.load(f).keys())

    with FAMILY_MAPPING_PATH.open() as f:
        family_mapping = sorted(json.load(f).keys())

    baseline = set(labels)
    sources = {'labels': labels, 'family_mapping': family_mapping}

    for name, path in DATASET_PATHS.items():
        sources[name] = folder_classes(path)

    print('Consistency check for dataset/model artifacts')
    print('=' * 60)
    for name, classes in sources.items():
        class_set = set(classes)
        missing = sorted(baseline - class_set)
        extra = sorted(class_set - baseline)
        status = 'OK' if not missing and not extra else 'MISMATCH'
        print(f'{name:13s} | classes={len(class_set):2d} | status={status}')
        if missing:
            print(f'  missing: {missing}')
        if extra:
            print(f'  extra:   {extra}')

    if all(set(classes) == baseline for classes in sources.values()):
        print('\nAll dataset folders, labels, and family mappings are consistent.')
    else:
        raise SystemExit('\nConsistency check failed.')


if __name__ == '__main__':
    main()
