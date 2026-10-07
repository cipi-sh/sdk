#!/usr/bin/env python3
"""Insert the PHP SDK chapter into the cipi.sh website. One-shot."""

from __future__ import annotations

import pathlib
import re
import subprocess
import sys

WEBSITE = pathlib.Path("/Users/andreapollastri/Documents/GitHub/website")
MAIN = pathlib.Path("/Users/andreapollastri/Documents/GitHub/sdk/php-sdk-main.html").read_text()

SIDEBAR = """
            <div class="sidebar-group" data-chapter="php-sdk">
                <a href="/docs/php-sdk" class="sidebar-group-label">PHP SDK</a>
                <a href="/docs/php-sdk#sdk-install" class="sidebar-link">installation</a>
                <a href="/docs/php-sdk#sdk-client" class="sidebar-link">client</a>
                <a href="/docs/php-sdk#sdk-laravel" class="sidebar-link">Laravel</a>
                <a href="/docs/php-sdk#sdk-apps" class="sidebar-link">apps</a>
                <a href="/docs/php-sdk#sdk-deploy" class="sidebar-link">deploy &amp; jobs</a>
                <a href="/docs/php-sdk#sdk-domains" class="sidebar-link">domains &amp; SSL</a>
                <a href="/docs/php-sdk#sdk-data" class="sidebar-link">env, auth, databases</a>
                <a href="/docs/php-sdk#sdk-server" class="sidebar-link">server</a>
                <a href="/docs/php-sdk#sdk-security" class="sidebar-link">security</a>
                <a href="/docs/php-sdk#sdk-errors" class="sidebar-link">errors</a>
            </div>
"""

ANCHOR = """                <a href="/docs/cli-client#cli-requirements" class="sidebar-link">requirements</a>
            </div>

            <div class="sidebar-group" data-chapter="gui">"""

REPLACEMENT = """                <a href="/docs/cli-client#cli-requirements" class="sidebar-link">requirements</a>
            </div>
""" + SIDEBAR + """
            <div class="sidebar-group" data-chapter="gui">"""

DESC = "cipi/sdk — call the Cipi panel API from PHP, Laravel, and other frameworks with a Sanctum token."


def patch_sidebar(text: str, path: pathlib.Path) -> str:
    if 'data-chapter="php-sdk"' in text:
        return text
    if ANCHOR not in text:
        raise SystemExit(f"sidebar anchor missing in {path}")
    return text.replace(ANCHOR, REPLACEMENT, 1)


def build_page(template: str) -> str:
    start = template.index('<main class="docs-content"')
    end = template.index("</main>") + len("</main>")
    page = template[:start] + MAIN.strip() + "\n" + template[end:]
    page = patch_sidebar(page, pathlib.Path("php-sdk.html"))

    replacements = {
        "Cipi Docs — CLI Client": "Cipi Docs — PHP SDK",
        "cipi-cli — manage your Cipi servers remotely from the terminal. Apps, databases, SSL, deploys, and more via the REST API.": DESC,
        "https://cipi.sh/docs/cli-client": "https://cipi.sh/docs/php-sdk",
        '"name": "CLI Client"': '"name": "PHP SDK"',
        '"dateModified": "2026-10-01"': '"dateModified": "2026-10-07"',
    }
    for old, new in replacements.items():
        count = page.count(old)
        if count == 0:
            raise SystemExit(f"expected to replace {old!r}")
        page = page.replace(old, new)
    if "/docs/cli-client" in page.split("<main", 1)[0]:
        # head should no longer point at the CLI page; sidebar links are after main starts? 
        # sidebar is BEFORE main. Relative /docs/cli-client links must remain in the sidebar.
        pass
    if page.count("https://cipi.sh/docs/php-sdk") < 3:
        raise SystemExit("canonical URL was not rewritten")
    if 'data-chapter="php-sdk"' not in page:
        raise SystemExit("php-sdk sidebar missing on the new page")
    if 'data-chapter="cli-client"' not in page:
        raise SystemExit("cli sidebar was dropped")
    return page


def replace_once(path: pathlib.Path, old: str, new: str) -> None:
    text = path.read_text()
    if old not in text:
        raise SystemExit(f"pattern missing in {path}")
    path.write_text(text.replace(old, new, 1))


def main() -> None:
    docs = WEBSITE / "docs"
    template = (docs / "cli-client.html").read_text()
    page = build_page(template)
    (docs / "php-sdk.html").write_text(page)

    for html in sorted(docs.glob("*.html")):
        if html.name == "php-sdk.html":
            continue
        text = html.read_text()
        html.write_text(patch_sidebar(text, html))

    replace_once(
        docs / "cli-client.html",
        """                <a href="/docs/gui" class="page-nav-link page-nav-link--next">
                    <span class="page-nav-label">Next</span>
                    <span class="page-nav-title">Control Panel (GUI)</span>
                </a>""",
        """                <a href="/docs/php-sdk" class="page-nav-link page-nav-link--next">
                    <span class="page-nav-label">Next</span>
                    <span class="page-nav-title">PHP SDK</span>
                </a>""",
    )
    replace_once(
        docs / "gui.html",
        """                <a href="/docs/cli-client" class="page-nav-link page-nav-link--prev">
                    <span class="page-nav-label">Previous</span>
                    <span class="page-nav-title">CLI Client</span>
                </a>""",
        """                <a href="/docs/php-sdk" class="page-nav-link page-nav-link--prev">
                    <span class="page-nav-label">Previous</span>
                    <span class="page-nav-title">PHP SDK</span>
                </a>""",
    )
    replace_once(
        docs / "index.html",
        """                <div class="doc-chapter-card">
                    <h3><a href="/docs/cli-client">CLI Client</a></h3>
                    <p>Manage one or more Cipi servers remotely from your local terminal. Apps, databases, SSL, deploys, multi-server profiles, global status overview, and shell completion — all via the REST API.</p>
                </div>
                <div class="doc-chapter-card">
                    <h3><a href="/docs/advanced">Advanced</a></h3>""",
        """                <div class="doc-chapter-card">
                    <h3><a href="/docs/cli-client">CLI Client</a></h3>
                    <p>Manage one or more Cipi servers remotely from your local terminal. Apps, databases, SSL, deploys, multi-server profiles, global status overview, and shell completion — all via the REST API.</p>
                </div>
                <div class="doc-chapter-card">
                    <h3><a href="/docs/php-sdk">PHP SDK</a></h3>
                    <p><code>cipi/sdk</code> calls the panel API from PHP, Laravel, and other frameworks. One Sanctum token covers apps, deploys, domains, databases, and the server.</p>
                </div>
                <div class="doc-chapter-card">
                    <h3><a href="/docs/advanced">Advanced</a></h3>""",
    )
    replace_once(
        docs / "index.html",
        '"dateModified": "2026-10-01"',
        '"dateModified": "2026-10-07"',
    )
    replace_once(
        docs / "advanced.html",
        'Client wrapper: <a href="/docs/cli-client">cipi-cli</a>.',
        'Client wrappers: <a href="/docs/cli-client">cipi-cli</a> and the <a href="/docs/php-sdk">PHP SDK</a> (<code>cipi/sdk</code>).',
    )
    replace_once(
        docs / "build-search-index.py",
        '    "cli-client": "CLI Client",\n',
        '    "cli-client": "CLI Client",\n    "php-sdk": "PHP SDK",\n',
    )

    replace_once(
        WEBSITE / "sitemap.xml",
        """    <loc>https://cipi.sh/docs/cli-client</loc>
    <lastmod>2026-10-01</lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.8</priority>
  </url>""",
        """    <loc>https://cipi.sh/docs/cli-client</loc>
    <lastmod>2026-10-01</lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.8</priority>
  </url>
  <url>
    <loc>https://cipi.sh/docs/php-sdk</loc>
    <lastmod>2026-10-07</lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.8</priority>
  </url>""",
    )

    replace_once(
        WEBSITE / "llms.txt",
        "- [CLI Client](https://cipi.sh/docs/cli-client): Remote `cipi-cli` — manage servers from the terminal via the REST API\n",
        "- [CLI Client](https://cipi.sh/docs/cli-client): Remote `cipi-cli` — manage servers from the terminal via the REST API\n- [PHP SDK](https://cipi.sh/docs/php-sdk): `cipi/sdk` — call the panel API from PHP, Laravel, and other frameworks with a Sanctum token\n",
    )
    replace_once(
        WEBSITE / "llms.txt",
        "- [CLI Client](https://cipi.sh/docs/cli-client): Remote terminal client for apps, databases, SSL, deploys\n",
        "- [CLI Client](https://cipi.sh/docs/cli-client): Remote terminal client for apps, databases, SSL, deploys\n- [PHP SDK](https://cipi.sh/docs/php-sdk): Composer package `cipi/sdk` for PHP and Laravel\n",
    )
    replace_once(
        WEBSITE / "llms-full.txt",
        "- CLI Client (cipi-cli): https://cipi.sh/docs/cli-client\n",
        "- CLI Client (cipi-cli): https://cipi.sh/docs/cli-client\n- PHP SDK (cipi/sdk): https://cipi.sh/docs/php-sdk\n",
    )
    replace_once(
        WEBSITE / "llms-full.txt",
        "- Cipi Control Panel and API (https://cipi.sh/guides/cipi-gui-and-api): optional `cipi/api` (REST + 68 MCP tools + Swagger) and `cipi/gui` (Laravel 12 multi-server dashboard). Same API powers cipi-cli and WHMCS. CLI-first: both packages are opt-in; remove them and Cipi is a CLI again.\n",
        "- Cipi Control Panel and API (https://cipi.sh/guides/cipi-gui-and-api): optional `cipi/api` (REST + 68 MCP tools + Swagger) and `cipi/gui` (Laravel 12 multi-server dashboard). Same API powers cipi-cli, the PHP SDK (`cipi/sdk`), and WHMCS. CLI-first: both packages are opt-in; remove them and Cipi is a CLI again.\n",
    )

    i18n = WEBSITE / "netlify/edge-functions/i18n.js"
    replace_once(
        i18n,
        "  '/docs/cli-client': '/docs/client-cli',\n",
        "  '/docs/cli-client': '/docs/client-cli',\n  '/docs/php-sdk': '/docs/php-sdk',\n",
    )

    replace_once(
        WEBSITE / "discovery.html",
        """                <a href="/docs/cli-client#cli-install" class="tour-card">
                    <h4>Go CLI client</h4>""",
        """                <a href="/docs/php-sdk" class="tour-card">
                    <h4>PHP SDK</h4>
                    <p><code>cipi/sdk</code> calls the panel API from PHP, Laravel, Symfony, or a script. One Sanctum token, typed resources, and job polling.</p>
                    <span class="card-link">Deep dive <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round">
                            <line x1="5" y1="12" x2="19" y2="12" />
                            <polyline points="12 5 19 12 12 19" />
                        </svg></span>
                </a>
                <a href="/docs/cli-client#cli-install" class="tour-card">
                    <h4>Go CLI client</h4>""",
    )

    guide = WEBSITE / "guides/cipi-gui-and-api.html"
    text = guide.read_text()
    text = text.replace(
        'content="cipi api, cipi gui, cipi control panel, laravel rest api, cipi mcp, cipi dashboard, cipi-cli, laravel server panel"',
        'content="cipi api, cipi gui, cipi control panel, laravel rest api, cipi mcp, cipi dashboard, cipi-cli, cipi/sdk, php sdk, laravel server panel"',
        1,
    )
    text = text.replace(
        'Last updated: <time datetime="2026-08-22">August 22, 2026</time>',
        'Last updated: <time datetime="2026-10-07">October 7, 2026</time>',
        1,
    )
    text = text.replace(
        '<li><a href="#clients">cipi-cli and WHMCS on the same wire</a></li>',
        '<li><a href="#php-sdk">PHP SDK for Laravel and other frameworks</a></li>\n                <li><a href="#clients">cipi-cli, the PHP SDK, and WHMCS</a></li>',
        1,
    )
    text = text.replace(
        """            <li><strong>Freelancer with two VPS</strong> — API + <code>cipi-cli</code> from the laptop. The GUI is optional; a <code>prod</code> and a <code>staging</code> profile are enough.</li>""",
        """            <li><strong>Freelancer with two VPS</strong> — API + <code>cipi-cli</code> from the laptop, or <code>cipi/sdk</code> inside a Laravel app. The GUI is optional; a <code>prod</code> and a <code>staging</code> profile are enough.</li>""",
        1,
    )
    old_clients = """        <h2 id="clients">cipi-cli and WHMCS on the same wire</h2>
        <p>The <a class="inline" href="/docs/cli-client">CLI client</a> is a Go binary that talks REST from your laptop: apps, aliases, deploy, SSL, databases, global status, jobs. Same tokens, same multi-server profiles. Prefer the terminal? You do not need the GUI. Prefer the browser? You do not need <code>cipi-cli</code>. Need both on different days? Same API.</p>
        <pre class="code-pre"><code>$ cipi-cli api token add prod
$ cipi-cli prod apps list
$ cipi-cli prod deploy myapp
$ cipi-cli status</code></pre>
        <p>WHMCS is the third official client: hosting provisioning with no Composer, drop-in to the modules folder. None of the three replaces <code>cipi</code> on the server — they remote it.</p>"""
    new_clients = """        <h2 id="php-sdk">PHP SDK for Laravel and other frameworks</h2>
        <p>Until now the official clients of this API were the terminal (<code>cipi-cli</code>), the browser (the GUI), MCP, and WHMCS. <a class="inline" href="/docs/php-sdk"><code>cipi/sdk</code></a> is the PHP client: the same token, the same routes, from a Laravel app or any other PHP process. It does not SSH and it does not run <code>cipi</code> locally. Install it where your code runs:</p>
        <pre class="code-pre"><code>$ composer require cipi/sdk</code></pre>
        <pre class="code-pre"><code>use Cipi\\Sdk\\Cipi;

$cipi = Cipi::connect('https://api.example.com', getenv('CIPI_TOKEN'));
$cipi->apps()->list();
$cipi->wait($cipi->deploys()->start('shop'));</code></pre>
        <p>On Laravel 11, 12, or 13 the service provider is discovered for you. Set <code>CIPI_BASE_URL</code> and <code>CIPI_TOKEN</code>, then call the <code>Cipi</code> facade or inject <code>Cipi\\Sdk\\Cipi</code>. Symfony and plain scripts use <code>Cipi::connect()</code> and pass the instance through their own container. HTTPS is required, redirects are not followed, and the token stays out of logs and exception messages. Full method list, job polling, and the error map: <a class="inline" href="/docs/php-sdk">PHP SDK</a>.</p>

        <h2 id="clients">cipi-cli, the PHP SDK, and WHMCS</h2>
        <p>The <a class="inline" href="/docs/cli-client">CLI client</a> is a Go binary that talks REST from your laptop: apps, aliases, deploy, SSL, databases, global status, jobs. Same tokens, same multi-server profiles. Prefer the terminal? You do not need the GUI. Prefer PHP? Use <a class="inline" href="/docs/php-sdk"><code>cipi/sdk</code></a>. Prefer the browser? You do not need either. Need all of them on different days? Same API.</p>
        <pre class="code-pre"><code>$ cipi-cli api token add prod
$ cipi-cli prod apps list
$ cipi-cli prod deploy myapp
$ cipi-cli status</code></pre>
        <p>WHMCS is another official client: hosting provisioning with no Composer, drop-in to the modules folder. None of these replaces <code>cipi</code> on the server — they remote it.</p>"""
    if old_clients not in text:
        raise SystemExit("guide clients section missing")
    text = text.replace(old_clients, new_clients, 1)
    text = text.replace(
        '"name": "What is the difference between REST, MCP and cipi-cli?",',
        '"name": "What is the difference between REST, MCP, cipi-cli and the PHP SDK?",',
        1,
    )
    text = text.replace(
        '"text": "The same API, three clients. REST is for scripts, CI and WHMCS. MCP is for AI agents (Cursor, VS Code, Claude). cipi-cli is the laptop terminal. The GUI is the fourth client, built for humans."',
        '"text": "The same API, several clients. REST is the wire. cipi/sdk is that wire from PHP and Laravel. MCP is for AI agents (Cursor, VS Code, Claude). cipi-cli is the laptop terminal. The GUI is the client built for humans. WHMCS provisions hosting on the same routes."',
        1,
    )
    text = text.replace(
        "<div class=\"faq-item\"><h3>What is the difference between REST, MCP and cipi-cli?</h3><p>The same API, three clients. REST is for scripts, CI and WHMCS. MCP is for AI agents (Cursor, VS Code, Claude). <code>cipi-cli</code> is the laptop terminal. The GUI is the fourth client, built for humans.</p></div>",
        "<div class=\"faq-item\"><h3>What is the difference between REST, MCP, cipi-cli and the PHP SDK?</h3><p>The same API, several clients. REST is the wire. <a class=\"inline\" href=\"/docs/php-sdk\"><code>cipi/sdk</code></a> is that wire from PHP and Laravel. MCP is for AI agents (Cursor, VS Code, Claude). <code>cipi-cli</code> is the laptop terminal. The GUI is the client built for humans. WHMCS provisions hosting on the same routes.</p></div>",
        1,
    )
    text = text.replace(
        '<a href="/docs/cli-client">CLI client</a>',
        '<a href="/docs/php-sdk">PHP SDK</a>\n            <a href="/docs/cli-client">CLI client</a>',
        1,
    )
    guide.write_text(text)

    replace_once(
        WEBSITE / "guides/index.html",
        "<p>What the two optional extensions unlock: REST, MCP, Swagger, a multi-server dashboard, server cockpit and automation — without leaving the CLI.</p>",
        "<p>What the two optional extensions unlock: REST, MCP, Swagger, the PHP SDK, a multi-server dashboard, server cockpit and automation — without leaving the CLI.</p>",
    )

    subprocess.run([sys.executable, str(docs / "build-search-index.py")], check=True, cwd=WEBSITE)
    print("updated website")


if __name__ == "__main__":
    main()
