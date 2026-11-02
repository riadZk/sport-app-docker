#!/bin/bash
# =============================================================================
# deploy-v1.sh — Restricted deployment entrypoint for Sport App v1
#
# This script is the ONLY command the GitHub Actions deploy SSH key is
# permitted to execute. It is referenced in authorized_keys via:
#
#   command="/home/deploy/bin/deploy-v1.sh",no-port-forwarding,\
#   no-X11-forwarding,no-agent-forwarding,no-pty ssh-ed25519 AAAA... deploy-key
#
# What this restriction means:
#   - The key cannot open an interactive shell
#   - The key can only trigger this script, and ONLY when the client
#     sends the exact command "deploy-v1" (validated via SSH_ORIGINAL_COMMAND)
#   - Port forwarding, agent forwarding, and X11 are all disabled
#   - No TTY is allocated, preventing interactive programs
#
# Ownership requirement (enforced by VPS setup, not by this script):
#   /home/deploy/bin and this file MUST be owned by root and NOT writable
#   by the deploy user. Otherwise the deploy user could modify this script
#   and bypass all restrictions on next SSH invocation.
#
# IMPORTANT — Docker group root-equivalence:
#   The deploy user is (or will be) a member of the "docker" group.
#   Docker group membership is EQUIVALENT TO ROOT on the host because:
#     docker run -v /:/host ubuntu chroot /host
#   gives a full root shell. Group membership alone is NOT isolation.
#   The real protection layers are:
#     1. This script is the ONLY executable path via the restricted key.
#     2. The script itself only touches /home/projects/sport-app-v1.
#     3. All Docker commands use -p sport-app-v1 (project isolation).
#   If the deploy user's shell account is ever compromised (e.g. via a
#   separate password or another key), the attacker has root. Treat the
#   deploy user as a service account: no password login, no other keys.
# =============================================================================

set -euo pipefail

# Hard-code the project directory — do not accept any input
PROJECT_DIR="/home/projects/sport-app-v1"
DEPLOY_SCRIPT="${PROJECT_DIR}/deploy.sh"

# --- Guard: only accept the exact command "deploy-v1" -------------------------
# authorized_keys forces this script via command=... so SSH_ORIGINAL_COMMAND
# contains whatever the client sent. We accept ONLY the exact string
# "deploy-v1" — anything else (empty, other commands, shell tricks) is
# rejected. This prevents the key from being used as a general shell.
if [[ "${SSH_ORIGINAL_COMMAND:-}" != "deploy-v1" ]]; then
    echo "ERROR: This key is restricted to deployment only." >&2
    exit 1
fi

# --- Guard: project directory must exist and contain deploy.sh ----------------
if [[ ! -d "$PROJECT_DIR" ]]; then
    echo "ERROR: Project directory $PROJECT_DIR not found." >&2
    exit 1
fi

if [[ ! -f "$DEPLOY_SCRIPT" ]]; then
    echo "ERROR: $DEPLOY_SCRIPT not found." >&2
    exit 1
fi

# --- Guard: refuse to run as root ----------------------------------------------
if [[ "$(id -u)" -eq 0 ]]; then
    echo "ERROR: Deployment must not run as root." >&2
    exit 1
fi

# --- Run the deployment ---------------------------------------------------------
cd "$PROJECT_DIR"
exec bash "$DEPLOY_SCRIPT"
