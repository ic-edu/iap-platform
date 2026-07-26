# Result Engine Architecture — IAP

`ResultEngine` bertanggung jawab terhadap evaluasi akhir attempt:
- Final Score & Pass Score Comparison
- Section Scores
- Pass / Fail Determination
- Percentage & Letter Grade (A+, A, B, C, D)
- Completion Status Formatting

Metode `generateResult(Attempt $attempt)` menyajikan struktur data terpadu tanpa bergantung pada framework HTTP Controller.
