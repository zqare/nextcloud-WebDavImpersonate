# WebDAV Impersonate - Vollständiger Implementierungsplan

## 🎯 Projektübersicht

**App Name**: WebDAV Impersonate  
**Zweck**: Sicheres User-Impersonating über WebDAV `X-Impersonate-User` Header  
**Lizenz**: AGPL-3.0-or-later (Pflicht für Nextcloud App Store)  
**Ziel-Nextcloud**: Hub 24-26 (Version 31-34.x)  
**PHP-Version**: 8.1+  

## 📋 Funktionsanforderungen

### Core Features
- [x] **WebDAV Header-Interception**: `X-Impersonate-User: targetuser`
- [x] **Gruppenbasierte Berechtigungen**: Dual-Group-System
  - **Imitierer-Gruppen**: Dürfen impersonieren (z.B. service-accounts)
  - **Imitierbare-Gruppen**: Dürfen impersoniert werden (z.B. sales-team)
- [x] **Real-time Validation**: Gruppenmitgliedschaft bei jedem Request
- [x] **Audit Logging**: Alle Versuche mit konfigurierbarem Log-Level
- [x] **Admin-UI**: Vue.js basierte Konfigurationsoberfläche
- [x] **Fail-Secure**: Leere Gruppenlisten = 100% blockiert

### Use Cases
- **Service Accounts** → Automatisierte Datei-Operationen als Endnutzer
- **Support-Teams** → Kunden-Dateien bearbeiten ohne Passwort-Sharing
- **CI/CD Pipelines** → Deployments als verschiedene User
- **Backup-Tools** → Dateizugriffe als Ziel-User ohne App-Passwords

## 🏗️ Architektur

### Event-Flow
```
HTTP Request (X-Impersonate-User: Steffen)
→ SabrePluginAddEvent (Nextcloud 28+)
→ SabrePluginListener::handle()
→ ImpersonatePlugin::beforeMethod()
→ ImpersonateService::impersonate()
  → isCallerInImpersonatorGroups()
  → isTargetInImitateeGroups()
  → IUserSession::setUser(targetUser)
  → LoggerInterface (konfigurierbares Level)
→ WebDAV Operation als targetUser
```

### Dateistruktur
```
webdav_impersonate/
├── appinfo/
│   ├── info.xml                  # App-Metadaten
│   └── routes.php                # API-Routen
├── lib/
│   ├── AppInfo/Application.php    # Bootstrap / DI-Container
│   ├── Controller/AdminSettingsController.php
│   ├── DAV/
│   │   ├── ImpersonatePlugin.php  # SabreDAV Plugin (Kernlogik)
│   │   └── SabrePluginListener.php
│   ├── Settings/
│   │   ├── AdminSettings.php
│   │   └── AdminSection.php
│   └── Service/ImpersonateService.php
├── src/
│   ├── views/AdminSettings.vue   # Vue.js Admin-UI
│   └── main.js
├── templates/admin.php
├── tests/
│   ├── unit/
│   │   ├── DAV/ImpersonatePluginTest.php
│   │   └── Service/ImpersonateServiceTest.php
│   └── bootstrap.php
├── docs/
│   ├── developer.md
│   └── setup.md
├── img/app.svg
├── composer.json
├── package.json
├── LICENSE (AGPL-3.0)
├── README.md
└── CHANGELOG.md
```

## 🔧 technische Spezifikationen

### Backend (PHP)
- **PSR-12 Coding Style** mit `strict_types=1`
- **Nur OCP\* Interfaces** (keine OC\* internen Klassen)
- **Dependency Injection** via Constructor
- **SabreDAV Plugin** registriert über `SabrePluginAddEvent`
- **Konfiguration** via `IConfig::setAppValue/getAppValue`
- **Logging** via `LoggerInterface`

### Frontend (Vue.js)
- **@nextcloud/vue** Components
- **@nextcloud/axios** für API-Calls
- **@nextcloud/router** für URL-Generierung
- **NcSelect** mit Live-Suche für Gruppen-Autocomplete
- **NcSettingsSection** für Admin-Integration

### Datenbank-Speicherung
```sql
-- Tabelle: oc_appconfig
INSERT INTO oc_appconfig VALUES 
('webdav_impersonate', 'impersonator_groups', '["service-accounts"]'),
('webdav_impersonate', 'imitatee_groups', '["sales-team"]'),
('webdav_impersonate', 'log_level', '"info"');
```

## 📚 Dokumentations-Typen

### 1. docs/developer.md
- Architektur-Übersicht
- Key Classes mit Verantwortlichkeiten
- Event-Flow Diagramm
- HTTP Header Spezifikation
- Extending Guidelines
- Testing Instructions

### 2. docs/setup.md
- Requirements (Nextcloud ≥ 28, PHP ≥ 8.1)
- Installation (App Store vs Manual)
- Admin Configuration Steps
- Usage Examples (curl mit Headers)
- Security Notes
- Troubleshooting

### 3. API Documentation
- REST Endpoints (/api/settings, /api/groups)
- Request/Response Formats
- Error Handling
- Authentication Requirements

### 4. README.md
- Quick Start Guide
- Features Overview
- Use Cases
- Installation Instructions
- License Information

## ⚖️ Lizenz & Compliance

### AGPL-3.0-or-later Anforderungen
- [x] **Jede PHP-Datei** mit AGPL-Header-Kommentar
- [x] **LICENSE Datei** mit vollem AGPL-3.0 Text
- [x] **composer.json** mit `"license": "AGPL-3.0-or-later"`
- [x] **appinfo/info.xml** mit `<licence>agpl</licence>`

### Nextcloud App Store Compliance
| Anforderung | Status | Umsetzung |
|-------------|--------|-----------|
| AGPL-3.0 Lizenz | ✅ | `<licence>agpl</licence>` |
| Kein "Nextcloud" im Namen | ✅ | "WebDAV Impersonate" |
| Nur Public OCP API | ✅ | Nur `OCP\*` Interfaces |
| Min/Max Version | ✅ | `min-version="28" max-version="32"` |
| Admin Kontakt | ✅ | `mail` + GitHub Issues |
| Unit Tests | ✅ | PHPUnit 10 mit Mocks |
| Keine Trademarks | ✅ | Eigenständiger Name |

## 🧪 Testing-Strategie

### Unit Tests (PHPUnit 10)
```php
// ImpersonateServiceTest.php
- testImpersonateThrowsNotAuthenticatedWhenNoUser()
- testImpersonateThrowsForbiddenWhenCallerNotInImpersonatorGroups()
- testImpersonateThrowsForbiddenWhenTargetNotInImitateeGroups()
- testImpersonateSuccessfullyWithValidGroupPermissions()
- testLogLevelFiltering()
- testGetSetImpersonatorGroups()
- testGetSetImitateeGroups()

// ImpersonatePluginTest.php
- testBeforeMethodDoesNothingWithoutHeader()
- testBeforeMethodCallsImpersonateWithHeader()
- testBeforeMethodPassesCorrectHttpMethod()
```

### Integration Tests
- WebDAV Requests mit curl
- Admin UI Konfiguration
- Database Config Persistence
- Log Output Validation

### Test-Execution
```bash
# Unit Tests
./vendor/bin/phpunit tests/unit/

# Integration Tests (Docker)
docker exec --user www-data nextcloud php occ app:list
docker exec --user www-data nextcloud php occ config:app:get webdav_impersonate
```

## 🚀 Implementierungs-Phasen

### Phase 1: Core DAV Plugin ✅
- [x] ImpersonatePlugin.php (SabreDAV Plugin)
- [x] SabrePluginListener.php (Event Registration)
- [x] ImpersonateService.php (Business Logic)
- [x] Basic Unit Tests

### Phase 2: Admin Backend
- [ ] AdminSettingsController.php (REST API)
- [ ] AdminSettings.php + AdminSection.php
- [ ] routes.php Erweiterung
- [ ] templates/admin.php

### Phase 3: Admin Frontend
- [ ] AdminSettings.vue (Dual-Group Select)
- [ ] src/main.js (Vue Mount)
- [ ] img/app.svg (Icon)
- [ ] package.json + Build

### Phase 4: Testing & Documentation
- [ ] Complete Unit Test Coverage
- [ ] docs/developer.md
- [ ] docs/setup.md
- [ ] README.md + CHANGELOG.md

### Phase 5: App Store Preparation
- [ ] Final Testing in Docker
- [ ] App Store Compliance Check
- [ ] GitHub Release v1.0.0
- [ ] App Store Submission

## 🔍 Debugging & Monitoring

### Logging
```php
// Konfigurierbare Log-Level
'debug', 'info', 'warning', 'error'

// Log-Format
[info|webdav_impersonate] Impersonation: serviceuser → targetuser [PUT]
```

### Debug Commands
```bash
# Nextcloud Logs
docker exec --user www-data nextcloud tail -f data/nextcloud.log | grep webdavimpersonate

# Config Inspection
docker exec --user www-data nextcloud php occ config:app:list webdav_impersonate

# App Status
docker exec --user www-data nextcloud php occ app:list | grep webdavimpersonate
```

## 📈 Erfolgskriterien

### Functional
- [ ] WebDAV Request mit `X-Impersonate-User` funktioniert
- [ ] Gruppen-Validierung funktioniert beidseitig
- [ ] Admin-UI speichert Konfiguration korrekt
- [ ] Logging funktioniert mit konfigurierbaren Levels

### Quality
- [ ] 100% Unit Test Coverage
- [ ] PSR-12 Compliance
- [ ] AGPL-3.0 Headers in allen Dateien
- [ ] Nextcloud App Store Compliance

### Performance
- [ ] < 5ms Overhead für WebDAV Requests
- [ ] Keine DB-Queries pro Request (nur Config-Cache)
- [ ] Memory Usage < 1MB additional

## 🔄 Wartung & Updates

### Versionierung
- **Semantic Versioning** (MAJOR.MINOR.PATCH)
- **CHANGELOG.md** für jede Version
- **GitHub Tags** für Releases
- **App Store** automatische Updates

### Backward Compatibility
- **Config-Keys** bleiben stabil
- **API-Endpoints** versioniert
- **Database Schema** unverändert (nur oc_appconfig)

---

**Status**: Phase 1 abgeschlossen, Phase 2 bereit für Implementation  
**Nächster Schritt**: Admin Backend Implementation (Controller + Settings)
