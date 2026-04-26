# Local Preview

Slate Ops is a WordPress plugin. It cannot preview by itself without a WordPress runtime.

This repo uses `@wordpress/env` for local preview.

## Requirements

- Docker installed and running
- Node / npm installed
- npm registry access

## Setup

```bash
npm install
npm run wp-env:start