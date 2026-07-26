# Analytics & Item Analysis Architecture — IAP

## Overview
Komponen Analytics pada IAP terbagi dua:
1. **`AnalyticsEngine`**: Menyiapkan statistik tingkat platform (Average Score, Pass Rate, Active Candidates, Certificates Count) dengan sistem caching 5 menit.
2. **`ItemAnalysisService`**: Melakukan analisis butir soal (Correct %, Wrong %, Skipped %, Difficulty Index) untuk evaluasi kualitas soal CBT.
