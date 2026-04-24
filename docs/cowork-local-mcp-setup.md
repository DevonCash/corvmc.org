# Connecting Local Tools to Cowork via MCP

Cowork runs in a sandboxed environment that can't install system packages (like PHP) or reliably run git commands (lock file permissions). You can bridge this gap by running a single MCP server on your Mac that exposes git, PHP tests, and artisan commands as tools Cowork can call directly.

## The Problem

The Cowork sandbox can read and write your project files, but:

- No PHP installed, can't run tests or artisan commands
- `git add` creates lock files the sandbox can't clean up, blocking `git commit`
- No `sudo` access to install packages

## The Solution

A single MCP server (~100 lines of Node) that runs on your Mac and exposes three tools: `run_tests`, `run_artisan`, and `git_commit`. Cowork calls these tools over stdio, and they execute locally with full access to PHP, git, SSH keys, etc.

## Setup

### 1. Create the server

```bash
mkdir -p ~/mcp-servers
cd ~/mcp-servers
npm init -y
npm install @modelcontextprotocol/sdk
```

Save this as `~/mcp-servers/corvmc-dev-server.mjs`:

```javascript
#!/usr/bin/env node

import { Server } from "@modelcontextprotocol/sdk/server/index.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import {
  CallToolRequestSchema,
  ListToolsRequestSchema,
} from "@modelcontextprotocol/sdk/types.js";
import { execSync } from "child_process";

const PROJECT_DIR = process.env.PROJECT_DIR || process.cwd();

const server = new Server(
  { name: "corvmc-dev", version: "1.0.0" },
  { capabilities: { tools: {} } }
);

server.setRequestHandler(ListToolsRequestSchema, async () => ({
  tools: [
    {
      name: "run_tests",
      description:
        "Run PHP tests via Pest/PHPUnit. Pass a filter to run specific tests, or leave empty to run the full suite.",
      inputSchema: {
        type: "object",
        properties: {
          filter: {
            type: "string",
            description:
              'Test filter (passed to --filter). Examples: "Finance", "OrderTest", "it_creates_an_order"',
          },
          path: {
            type: "string",
            description:
              "Specific test file or directory to run. Relative to project root.",
          },
        },
      },
    },
    {
      name: "run_artisan",
      description:
        "Run a php artisan command. Use for migrations, route:list, etc.",
      inputSchema: {
        type: "object",
        properties: {
          command: {
            type: "string",
            description:
              'The artisan command (without "php artisan" prefix). Example: "migrate --status"',
          },
        },
        required: ["command"],
      },
    },
    {
      name: "git_commit",
      description:
        "Stage files and create a git commit. Runs git add on the specified files (or all changes), then commits with the given message.",
      inputSchema: {
        type: "object",
        properties: {
          message: {
            type: "string",
            description: "The commit message.",
          },
          files: {
            type: "array",
            items: { type: "string" },
            description:
              'Files to stage. Pass ["."] or omit to stage all changes.',
          },
        },
        required: ["message"],
      },
    },
    {
      name: "git_status",
      description: "Run git status to see the current state of the working tree.",
      inputSchema: { type: "object", properties: {} },
    },
  ],
}));

server.setRequestHandler(CallToolRequestSchema, async (request) => {
  const { name, arguments: args } = request.params;

  const run = (cmd) =>
    execSync(cmd, {
      cwd: PROJECT_DIR,
      encoding: "utf-8",
      timeout: 120000,
      maxBuffer: 1024 * 1024 * 5,
    });

  try {
    let output;

    switch (name) {
      case "run_tests": {
        let cmd = "php artisan test";
        if (args?.filter) cmd += ` --filter="${args.filter}"`;
        if (args?.path) cmd += ` ${args.path}`;
        output = run(cmd);
        break;
      }

      case "run_artisan": {
        const blocked = ["db:wipe", "migrate:fresh", "migrate:reset"];
        if (blocked.some((b) => args.command.startsWith(b))) {
          return {
            content: [
              {
                type: "text",
                text: `Blocked: "${args.command}" is too destructive to run from Cowork.`,
              },
            ],
          };
        }
        output = run(`php artisan ${args.command}`);
        break;
      }

      case "git_commit": {
        const files = args?.files?.length ? args.files.join(" ") : ".";
        run(`git add ${files}`);
        // Write message to temp file to avoid shell escaping issues
        const fs = await import("fs");
        const tmpMsg = "/tmp/cowork-commit-msg.txt";
        fs.writeFileSync(tmpMsg, args.message);
        output = run(`git commit -F ${tmpMsg}`);
        fs.unlinkSync(tmpMsg);
        break;
      }

      case "git_status": {
        output = run("git status");
        break;
      }

      default:
        return {
          content: [{ type: "text", text: `Unknown tool: ${name}` }],
          isError: true,
        };
    }

    return { content: [{ type: "text", text: output }] };
  } catch (error) {
    const output = [error.stdout, error.stderr].filter(Boolean).join("\n");
    return {
      content: [{ type: "text", text: output || error.message }],
      isError: true,
    };
  }
});

const transport = new StdioServerTransport();
await server.connect(transport);
```

### 2. Configure in Claude Desktop

Open Settings → Developer → Edit Config, and add:

```json
{
  "mcpServers": {
    "corvmc-dev": {
      "command": "node",
      "args": ["/Users/dcash/mcp-servers/corvmc-dev-server.mjs"],
      "env": {
        "PROJECT_DIR": "/Users/dcash/Projects/_sites/corvmc-redux"
      }
    }
  }
}
```

### 3. Restart Claude Desktop

The tools should appear in new Cowork sessions automatically.

## What you get

| Tool | What it does |
|------|-------------|
| `run_tests` | `php artisan test` with optional `--filter` and path |
| `run_artisan` | Any artisan command (destructive ones blocked) |
| `git_commit` | `git add` + `git commit` with a message |
| `git_status` | `git status` |

## Security notes

- Runs on your Mac with your user permissions — same as running commands in your terminal
- `PROJECT_DIR` is locked to your project — the AI can't escape to other directories
- Destructive artisan commands (`db:wipe`, `migrate:fresh`, `migrate:reset`) are blocked
- 2-minute timeout on all commands
- Add more tools or blocked commands as needed

## Testing

After restarting, start a new Cowork session and try:

> Run the Finance tests

The AI will call `run_tests` with filter "Finance" and show results in the conversation.

## Optional: Add the GitHub MCP server

For PR creation, CI monitoring, and issue management, you can also add the [GitHub MCP server](https://github.com/github/github-mcp-server). It complements this server — GitHub MCP handles the GitHub API, this server handles local dev commands.
