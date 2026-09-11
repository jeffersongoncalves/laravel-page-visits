# Changelog

All notable changes to this project will be documented in this file.

## 1.0.0 - 2026-09-11

Initial release.

- Terminable `TrackPageVisit` middleware — zero added latency, captures
  after the response is sent
- `TrackPageVisitJob` — full UA parse, IP anonymize/hash, referer
  classification, GeoIP resolution off the request thread
- Captures: path, route name, method, status code, device/browser/OS,
  geo, referer, browser language, app locale, UTM params
- Per-field `track_*` config toggles, glob-based path exclusion,
  `compliance.analytics_only` mode
- GDPR-style personal data export/erasure via `PageVisitPersonalDataService`
- Auto-registers into the `web` middleware group (toggle-able)

Built on jeffersongoncalves/laravel-visitor-fingerprint 1.0.0 for every
fingerprinting primitive.

## [Unreleased]
