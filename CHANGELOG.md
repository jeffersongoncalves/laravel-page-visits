# Changelog

All notable changes to this project will be documented in this file.

## 1.0.3 - 2026-09-11

Fixed

- Excluded paths now cover the bare root of a prefix, not just its
  sub-paths — Str::is('horizon/*', 'horizon') is false, so visiting
  /horizon itself leaked into page_visits despite horizon/* being
  excluded. Same gap fixed for admin/*, app/*, _debugbar/*, og/*.

## 1.0.2 - 2026-09-11

Fixed

- Routes whose path is unpredictable (e.g. a short-link redirect
  fallback route matching an arbitrary key at the root) leaked into
  page_visits, double-tracking the same visit already recorded by
  the redirect package itself. Fixes #2.

Added

- page-visits.exclude_route_names: glob-matched against the current
  route name, for routes a path glob can't target. Empty by default —
  add your app's redirect-style route names after publishing config.
- sitemap.xml, llms.txt, robots.txt now excluded by default (path
  glob) — never real page views.

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
