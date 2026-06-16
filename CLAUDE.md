# Qordy — Enterprise-Grade Multi-Agent System

## 🚀 SİSTEM MİMARİSİ

```
┌─────────────────────────────────────────────────────────────┐
│ LEVEL 5: HEADLESS NIGHTLY JOB (GitHub Actions)              │
│ cron: 0 2 * * * → tam otomatik audit → TECH_DEBT.md        │
├─────────────────────────────────────────────────────────────┤
│ LEVEL 4: ADVERSARIAL VERIFY PANEL                        │
│ 3 lens → consensus rejection loop → super accurate        │
├─────────────────────────────────────────────────────────────┤
│ LEVEL 3: WORKTREE ISOLATION                               │
│ each agent → blast radius control → safe parallel refactor │
├─────────────────────────────────────────────────────────────┤
│ LEVEL 2: ORCHESTRATOR (pure coordination)                │
│ NO Read/Write/Bash → TaskCreate + SendMessage only        │
├─────────────────────────────────────────────────────────────┤
│ LEVEL 1: HOOKS (deterministic quality gates)              │
│ PreToolUse/PostToolUse/TaskCompleted → auto guardrails   │
└─────────────────────────────────────────────────────────────┘
```

## 📋 Environment
- Path: `/var/www/vhosts/qordy.com/httpdocs`
- PHP: `/opt/plesk/php/8.3/bin/php`
- DB: qordy @ localhost:3306
- Git: Multi-branch with worktree isolation
- Version Control: Multi-repo structure

## ⚙️ CRITICAL CONFIGURATIONS

### Multi-Agent Orchestration
```yaml
Agent Types:
  - Scout: Code inventory & baseline (max: 5)
  - Architect: System design & refactoring (max: 2)
  - Security: OWASP Top 10 + Zero Trust (max: 3)
  - Performance: N+1, Cache, Optimization (max: 2)
  - CodeHealth: DRY, hardcode detection, coverage (max: 3)
  - Tester: Unit/Integration/E2E automation (max: 3)
  - Refactor: Transformation engine (max: 4)
  - Reviewer: Adversarial final review (max: 2)
```

### Hook-Based Quality Gates
```json
{
  "hooks": {
    "PreToolUse": [{
      "matcher": "Bash",
      "hooks": [{ "type": "command", "command": "bash .claude/guards/bash-guard.sh" }]
    }],
    "PostToolUse": [{
      "matcher": "Write",
      "hooks": [{ "type": "command", "command": "php -l $FILE && phpcs $FILE" }]
    }],
    "TaskCompleted": [{
      "hooks": [{ "type": "command", "command": "bash .claude/gates/quality-gate.sh" }]
    }],
    "SubagentStart": [{
      "hooks": [{ "type": "command", "command": "echo '[AGENT START] $(date)' >> .claude/logs/agent.log" }]
    }],
    "SubagentStop": [{
      "hooks": [{ "type": "command", "command": "echo '[AGENT STOP] $(date)' >> .claude/logs/agent.log" }]
    }]
  }
}
```

## 🔍 Enterprise Audit Flow

### Step 1: Scout Baseline
```
Output: TECHNICAL_STACK.md
- PHP: 8.3, Laravel/Framework detection
- Frontend: React/Vue/Angular analysis
- Database: Schema, ORM usage, N+1 risks
- Dependencies: Composer, NPM packages security
- Code Quality: Static analysis, coverage reports
```

### Step 2: Multi-Agent Parallel Audit
```bash
# Simultaneous execution
@qordy-architect     → AUDIT_ARCHITECTURE.md
@qordy-security      → AUDIT_SECURITY.md (OWASP Top 10)
@qordy-performance   → AUDIT_PERFORMANCE.md
@qordy-codehealth    → AUDIT_CODEHEALTH.md
@qordy-frontend      → AUDIT_FRONTEND.md
```

### Step 3: Adversarial Review
```yaml
Pattern: Blind Peer Review
- Security audits Security's findings
- Performance rejects performance bottlenecks
- Architect validates architecture violations
- CodeHealth refactors code smells
Consensus: Only findings surviving all lenses
```

### Step 4: Action Plan Generation
```yaml
AUDIT_MASTER.md:
  - Executive Summary
  - Risk Matrix: Critical/High/Medium/Low
  - Dependency Map
  - Technical Debt Quantification
  - ROI Analysis for fixes

AUDIT_ACTION_PLAN.md:
  - Sprint 1: Critical Security (1 week)
  - Sprint 2: Performance Hotspots (2 weeks)
  - Sprint 3: Architecture Refactor (3 weeks)
  - Sprint 4: Code Modernization (2 weeks)
  - Sprint 5: Documentation & Training (1 week)
```

## 🛡️ Quality Gates

### Pre-Execution (Hooks)
1. **Bash Guard**: Prevents dangerous commands
2. **Syntax Check**: PHP syntax validation
3. **CSRF Protection**: Token validation
4. **Rate Limiting**: Prevent DoS attacks

### Post-Execution (Automated)
1. **Code Quality**: PHPCS + PHPStan
2. **Security Scan**: SonarQube integration
3. **Performance**: Lighthouse scores
4. **Coverage**: PHPUnit + Codecov

## 🎯 Multi-Agent Execution Modes

### 1. On-Demand Mode
```bash
# Manual trigger
qordy-audit --full
qordy-refactor --domain auth
qordy-optimize --database-indexes
```

### 2. Headless Nightly
```yaml
GitHub Actions:
  - Trigger: push to main
  - Schedule: 0 2 * * *
  - Tasks:
      - Differential audit (changed files only)
      - Technical debt tracking
      - Code churn analysis
      - Dependency security updates
```

### 3. CI/CD Integration
```yaml
Pipeline:
  1. Build → Tests → Security Scan
  2. Multi-Agent Quality Check
  3. Deploy Preview
  4. E2E Tests
  5. Production Deploy (with agent sign-off)
```

## 📊 Monitoring & Metrics

### Agent Performance
- Success rate per agent type
- Average execution time
- Task completion rate
- Error detection rate

### System Health
- Code churn trends
- Technical debt ratio
- Security vulnerability metrics
- Performance regression tracking

## 🔧 CLI Commands

```bash
# Audit Operations
qordy-audit --full              # Complete audit
qordy-audit --diff-only         # Changed files since last commit
qordy-audit --security-only     # OWASP Top 10 focus
qordy-audit --performance-only  # N+1, cache, indexes

# Refactoring
qordy-refactor --database      # Schema optimization
qordy-refactor --frontend       # Component architecture
qordy-refactor --api           # Endpoint standardization
qordy-refactor --legacy        # Modernize deprecated code

# Maintenance
qordy-maintain --dependencies  # Update with security fixes
qordy-maintain --docs          # Auto-documentation
qordy-maintain --tests         # Generate test coverage
```

## 🚨 CRITICAL PATHS

### Security-Critical
- `/app/Http/Middleware/` → Authentication & Authorization
- `/app/Services/` → Business logic validation
- `/config/` → Environment & secrets
- `/storage/logs/` → Audit logs

### Performance-Critical
- `/app/Models/` → Query optimization
- `/app/Repositories/` → Database access patterns
- `/routes/` -> Route optimization
- `/cache/` → Caching strategy

## 🎨 Agent Configuration Files Location
```
~/.claude/agents/qordy-*.yml
~/.claude/hooks/
~/.claude/guards/
~/.claude/gates/
```

## 🎯 AUTO-SKILL ROUTING (Otomatik Skill Seçim)

### Kural
**Her tasarım/UI/UX revizyon talebi → Claude otomatik olarak en iyi skill'i seçer.**
User müdahale ETMEZ, sormaz, karar vermez.

### Skill Routing Tablosu

| Talep Tipi | Birincil | Yardımcı | Token |
|-----------|----------|----------|-------|
| Yeni sayfa/landing | `frontend-design` | `modern-web-design` | Min |
| Modern/trend | `modern-web-design` | - | Min |
| UX/hiyerarşi | `ui-ux-pro-max` | - | Min |
| Mevcut revize | `design-consultation` | - | Min |
| Çoklu alternatif | `design-shotgun` | - | Min |
| HTML çıktı | `design-html` | - | Min |
| Tasarım QA | `design-review` | `qa` | Min |
| Site tarama | `gstack browse` | - | Min |
| Performans | `gstack benchmark` | - | Min |

### Otomatik Tetikleme
- User tasarım/UI/UX/revizyon dendi → Yukarıdaki tabloya göre skill seç
- Sorgulama YOK — direkt çalıştır
- Tek skill yeterliyse BİRİNCİL'den fazlası YOK
- CodeGraph MCP zaten aktif — ek symbol arama YOK

### CodeGraph Güncelleme
- Her git commit/push sonrası otomatik güncellenir
- Session açılınca yüklenmez — lazy load (ilk sorguda)
- Kullanıcı müdahalesi GEREKMEDİĞİNDEN güncelleme KURALLARI:

```bash
# Manuel güncelleme isterse:
codegraph update --path /var/www/vhosts/qordy.com

# Otomatik kontrol (hook ile):
git post-commit -> codegraph sync (arka planda)
```

### YASAK
- ❌ "Hangi skill kullanayım?" diye SORMAMEK
- ❌ Aynı iş için 2+ skill paralel çağırma
- ❌ Skill kullanmadan direkt kod yazma
- ❌ Token israfı

---

## 📈 Success Metrics
- Security vulnerabilities: 0 (Critical/High)
- Performance score: >90 (Lighthouse)
- Code coverage: >80%
- Technical debt ratio: <15%
- Average build time: <5 minutes