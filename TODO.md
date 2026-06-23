# Statisty TODO

## High Priority

- [ ] Add explicit model, column, and relation allow-list configuration.
- [ ] Add configured business definitions for KPIs, charts, funnels, and cohorts.
- [ ] Add builder APIs for KPIs, charts, funnels, and cohorts.
- [ ] Add security tests for hidden columns, hidden relation columns, disabled models, policies, invalid queries, pagination limits, and CSV exports.
- [ ] Expand Laravel integration documentation.
- [ ] Add a clear authorization callback API.

## Analytics

- [ ] Add advanced funnels: conversion window, configurable strict ordering (`>` instead of `>=`), optional steps, segmentation, and source/campaign breakdowns.
- [ ] Add richer cohorts: retention percentage, cohort size, zero-filled empty periods, matrix export, and `activity_date` support separate from `created_at`.
- [ ] Add KPI previous-period comparison: current value, previous value, delta, and percentage change.
- [ ] Add segmentation/group-by metrics: count by status, sum revenue by country, active users by plan.
- [ ] Add timezone support everywhere, especially day/week/month bucket generation.

## Technique

- [ ] Add dedicated request objects or validators for endpoints instead of reading directly from `query()`.
- [ ] Standardize JSON error responses: `invalid_model`, `invalid_column`, `unauthorized`, and related API errors.
- [ ] Improve database abstraction in `ChartDataGenerator` for PostgreSQL, SQLite, and MySQL instead of relying mostly on MySQL SQL plus PHP fallback.
- [ ] Improve cache control with versioned keys, invalidation, and cache tags when available.
- [ ] Add paginated or streamed exports so CSV export does not load all rows into memory.

## DX / Package

- [ ] Rewrite README around real usage with practical snippets.
- [ ] Add changelog, CI badges, and quality tooling such as PHPStan, Pint, Pest, or PHPUnit CI.
- [ ] Add more Orchestra fixtures and integration tests.
- [ ] Add `statisty:doctor` to verify config, policies, assets, and exposed models.
- [ ] Add minimal frontend resources or a stable JSON contract for dashboard consumers.
