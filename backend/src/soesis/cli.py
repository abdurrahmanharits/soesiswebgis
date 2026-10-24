"""Account administration from the command line (composition root of the CLI).

  docker compose exec backend python -m soesis.cli create-admin
  docker compose exec backend python -m soesis.cli reset-password <e-mail or username>

Passwords are always typed at a hidden prompt, never passed as options.
"""

import os

import typer
from redis import Redis

from soesis.composition import build_identity_services
from soesis.modules.identity.application.demo_seed import DEMO_ACCOUNTS, DEMO_ORGANIZATION
from soesis.modules.identity.application.services import IdentityServices
from soesis.shared.config import get_settings
from soesis.shared.db import build_engine
from soesis.shared.kernel import ApplicationError, ValidationFailed

cli = typer.Typer(add_completion=False, no_args_is_help=True, help="SoESIS account administration.")

MAX_PASSWORD_PROMPTS = 3


def _services() -> IdentityServices:
    settings = get_settings()
    return build_identity_services(
        settings, build_engine(settings.database_url), Redis.from_url(settings.rate_limit_redis_url)
    )


def _fail(error: ApplicationError) -> None:
    typer.secho(error.message, fg=typer.colors.RED, err=True)
    if isinstance(error, ValidationFailed):
        for field, messages in error.errors.items():
            for message in messages:
                typer.secho(f"  {field}: {message}", fg=typer.colors.RED, err=True)
    raise typer.Exit(code=1)


def _ask_password(label: str) -> str:
    """Asks twice; a mismatch asks again instead of aborting (up to 3 times)."""
    for _ in range(MAX_PASSWORD_PROMPTS):
        password = typer.prompt(f"{label} (min. 8 characters)", hide_input=True)
        if typer.prompt("Repeat password", hide_input=True) == password:
            return password
        typer.secho("The passwords do not match. Please try again.", fg=typer.colors.YELLOW, err=True)
    typer.secho("Password confirmation does not match.", fg=typer.colors.RED, err=True)
    raise typer.Exit(code=1)


@cli.command("create-admin")
def create_admin(
    name: str = typer.Option(..., prompt=True, help="Display name"),
    username: str = typer.Option(..., prompt=True, help="3–30 lowercase letters, digits, . or _"),
    email: str = typer.Option(..., prompt="E-mail", help="Login e-mail"),
) -> None:
    """Create the first admin account (refused once an admin exists)."""
    services = _services()
    password = _ask_password("Password")
    try:
        admin = services.bootstrap.create_initial_admin(name, username, email, password)
    except ApplicationError as error:
        _fail(error)
    typer.secho(f"Admin {admin.username} created. Sign in at /login.", fg=typer.colors.GREEN)


@cli.command("reset-password")
def reset_password(login: str = typer.Argument(..., help="E-mail or username")) -> None:
    """Set a new password (signs the account out everywhere)."""
    services = _services()
    password = _ask_password("New password")
    try:
        user = services.bootstrap.reset_password(login, password)
    except ApplicationError as error:
        _fail(error)
    typer.secho(f"Password for {user.username} updated.", fg=typer.colors.GREEN)


@cli.command("seed-demo-users")
def seed_demo_users(
    reset_passwords: bool = typer.Option(
        False, "--reset-passwords", help="Also set the password of existing demo accounts"
    ),
) -> None:
    """Create demo accounts (demo.uploader, demo.curator, demo.curator2, demo.admin, demo.inactive).

    Password: SOESIS_SEED_PASSWORD if set, otherwise asked at a hidden prompt. Refused in production.
    """
    settings = get_settings()
    services = _services()
    password = os.environ.get("SOESIS_SEED_PASSWORD") or _ask_password("Password for all demo accounts")
    try:
        report = services.demo.seed(password, settings.env, reset_passwords=reset_passwords)
    except ApplicationError as error:
        _fail(error)

    for label, usernames in (
        ("created", report.created),
        ("password reset", report.updated),
        ("already existed", report.skipped),
    ):
        if usernames:
            typer.echo(f"{label}: {', '.join(usernames)}")

    typer.echo(f"\nOrganization: {DEMO_ORGANIZATION}")
    for account in DEMO_ACCOUNTS:
        status = "" if account.is_active else " (inactive)"
        typer.echo(f"  {account.username:<15} {account.role.label:<9}{status:<12} {account.purpose}")
    typer.secho("\nSign in at /login with any of these usernames and the password you chose.", fg=typer.colors.GREEN)


@cli.command("remove-demo-users")
def remove_demo_users() -> None:
    """Delete all demo.* accounts and their sessions (curation history keeps the name)."""
    settings = get_settings()
    try:
        removed = _services().demo.remove(settings.env)
    except ApplicationError as error:
        _fail(error)
    typer.echo(f"Removed: {', '.join(removed)}" if removed else "No demo accounts found.")


if __name__ == "__main__":
    cli()
