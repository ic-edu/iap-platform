# ADR 0007: Analytics and Psychometric Item Analysis Architecture

- **Status**: Accepted
- **Date**: 2026-07-26

## Context
Platform administrators require insights into test performance, pass rates, average completion times, and individual question metrics (difficulty index, correct/wrong percentages).

## Decision
1. **Analytics Engine**: `AnalyticsEngine` computes aggregate platform metrics with 5-minute Redis/Cache caching (`Cache::remember`).
2. **Item Analysis**: `ItemAnalysisService` computes question-level psychometric data (Correct %, Wrong %, Skipped %, Difficulty Index) dynamically from candidate `Answer` models.

## Consequences
Reduces database load through caching while supporting future psychometric expansion (e.g. Item Response Theory).
