import base64
import hashlib
import hmac
import json
import os
import re
import secrets
from datetime import datetime, timedelta, timezone
from urllib.parse import urlsplit

import azure.functions as func
from azure.core.exceptions import ResourceNotFoundError, ResourceExistsError
from azure.data.tables import TableServiceClient

app = func.FunctionApp(http_auth_level=func.AuthLevel.ANONYMOUS)
WALLET_RE = re.compile(r"^https://pay\.google\.com/gp/v/save/[A-Za-z0-9_.-]+$")
TOKEN_RE = re.compile(r"^[A-Za-z0-9_-]{32}$")


def table():
    connection = os.environ["SHORTLINK_STORAGE_CONNECTION"]
    client = TableServiceClient.from_connection_string(connection)
    return client.get_table_client(os.environ.get("SHORTLINK_TABLE", "GoogleWalletLinks"))


def public_base():
    url = os.environ["SHORTLINK_PUBLIC_BASE"].rstrip("/")
    parts = urlsplit(url)
    if parts.scheme != "https" or not parts.hostname or parts.username or parts.password or parts.query or parts.fragment:
        raise ValueError("Invalid SHORTLINK_PUBLIC_BASE")
    return url


def json_response(payload, status):
    return func.HttpResponse(json.dumps(payload), status_code=status, mimetype="application/json",
                             headers={"Cache-Control": "no-store"})


@app.route(route="shortlinks", methods=["POST"])
def register(req: func.HttpRequest) -> func.HttpResponse:
    expected = os.environ.get("SHORTLINK_REGISTRATION_SECRET", "")
    auth = req.headers.get("Authorization", "")
    if not expected or not hmac.compare_digest(auth, "Bearer " + expected):
        return json_response({"error": "Unauthorized"}, 401)
    if len(req.get_body()) > 16000:
        return json_response({"error": "Invalid request"}, 400)
    try:
        payload = req.get_json()
        destination = payload.get("wallet_url")
        if not isinstance(destination, str) or len(destination) > 12000 or not WALLET_RE.fullmatch(destination):
            return json_response({"error": "Invalid destination"}, 400)
        base = public_base()
        token = secrets.token_urlsafe(24)
        if not TOKEN_RE.fullmatch(token):
            raise ValueError("Invalid token generated")
        now = datetime.now(timezone.utc)
        expires = now + timedelta(days=int(os.environ.get("SHORTLINK_LIFETIME_DAYS", "180")))
        if not (1 <= int(os.environ.get("SHORTLINK_LIFETIME_DAYS", "180")) <= 365):
            raise ValueError("Invalid lifetime")
        # Tokens are opaque bearer credentials; only their SHA-256 hashes are stored as row keys.
        entity = {
            "PartitionKey": "google",
            "RowKey": hashlib.sha256(token.encode()).hexdigest(),
            "destination": destination,
            "expires": expires.isoformat(),
            "created": now.isoformat(),
        }
        table().create_entity(entity)
        return json_response({"short_url": base + "/g/" + token}, 201)
    except (ValueError, TypeError, KeyError, json.JSONDecodeError):
        return json_response({"error": "Invalid request or configuration"}, 400)
    except Exception:
        return json_response({"error": "Service unavailable"}, 503)


@app.route(route="g/{token}", methods=["GET"])
def resolve(req: func.HttpRequest) -> func.HttpResponse:
    token = req.route_params.get("token", "")
    if not TOKEN_RE.fullmatch(token):
        return json_response({"error": "Link not found"}, 404)
    try:
        row = table().get_entity("google", hashlib.sha256(token.encode()).hexdigest())
        expires = datetime.fromisoformat(row["expires"])
        if datetime.now(timezone.utc) >= expires or row.get("revoked", False):
            return json_response({"error": "Link unavailable"}, 410)
        destination = row["destination"]
        if not WALLET_RE.fullmatch(destination):
            return json_response({"error": "Link unavailable"}, 410)
        return func.HttpResponse(status_code=302, headers={
            "Location": destination, "Cache-Control": "no-store",
            "Referrer-Policy": "no-referrer", "X-Robots-Tag": "noindex, nofollow"
        })
    except ResourceNotFoundError:
        return json_response({"error": "Link not found"}, 404)
    except Exception:
        return json_response({"error": "Service unavailable"}, 503)
