# F6-I-R1 controlled publication

- Base and runtime source commit: d46f1ca0775c89c1b83b935079fc6abae1ab588e.
- Reviewed local candidate tree: b37caf4f31fa6e400c5a56e2e60f121fd52fd805.
- Reviewed source manifest SHA-256: e12ba99f6a88e6d786761a2c58846ac8714790ad0827666c7afb0596390b910a.
- F6-I-R1: LIMITED INDEPENDENT CODE REVIEW PASSED.
- Windows archive and extraction: 26 PASS / 0 FAIL. Linux umask 000, 002, 022 and 077: 14 PASS / 0 FAIL each.
- PHP 8.3.36 and 8.4.27: 21 installation checks in the reviewed R1 run and 125 targeted regression checks per version, all passing. PHPStan level 5, lint, pattern and document checks passed.
- R1 FULL and Chromium: NOT_RUN. Historical F6-I only: the 2 GiB full run recorded 1,098 PASS and one SUP-008 failure. The 4 GiB rerun recorded 1,137 PASS / 0 FAIL. The first failure's exact error code was not captured.
- R1 release ZIP SHA-256: 598331848384303895cd9a005606e2d5f8a97fef93d3886b2aaf3de686214432.
- R1 release manifest SHA-256: fd73674a2c92aa4dcc0d99035217abc3897b11cf6f06022bd03e8e5359b24486.
- Real cPanel and backup/restore: NOT_RUN. WHMCS migration: DEFERRED. G-O01, G-O02, G-O03, OPS-002 and OPS-003: OPEN. Staging and production: NOT AUTHORIZED.
