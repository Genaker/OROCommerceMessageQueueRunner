# Genaker Message Queue Debug Bundle

![Genaker Message Queue Debug](src/Genaker/Bundle/MessageQueueDebugBundle/message-queue-debug-banner.png)

CLI commands for debugging OroCommerce message queue processors. Run processors via CLI to debug with breakpoints, list all jobs with message counts, and process specific messages.

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](https://opensource.org/licenses/MIT)
[![OroCommerce](https://img.shields.io/badge/OroCommerce-6.1+-green.svg)](https://oroinc.com/)
[![PHP 8.1+](https://img.shields.io/badge/PHP-8.1+-purple.svg)](https://php.net/)

---

## Features

| Feature | Description |
|---------|-------------|
| **List jobs** | List all queues, registered processors, and message counts |
| **Process single message** | Run processor via CLI with `--message-limit=1` for debugging |
| **Specific processor** | Force a processor with `--processor` when queue has multiple |
| **Filter by queue** | List or process messages from a specific queue only |
| **JSON output** | Export job list as JSON for scripting |
| **Time limit** | Cap processing time with `--time-limit` |

---

## Installation

1. Add to `config/oro/bundles.yml`:

```yaml
bundles:
    - { name: Genaker\Bundle\MessageQueueDebugBundle\GenakerMessageQueueDebugBundle, priority: 1000 }
```

2. Run:

```bash
php bin/console cache:clear
```

---

## Commands

### `genaker:mq:list`

List all message queue destinations with their processors and message counts.

```bash
# List all queues and processors
php bin/console genaker:mq:list

# Filter by queue name
php bin/console genaker:mq:list --queue=default

# Output as JSON
php bin/console genaker:mq:list --json
```

**Output:** Table with Queue, Transport Queue, Processor, and Messages columns.

---

### `genaker:mq:process`

Process message(s) from the queue. Runs as a CLI command so you can debug with breakpoints.

```bash
# Process 1 message from default queue (default)
php bin/console genaker:mq:process -m 1

# Process from specific queue
php bin/console genaker:mq:process default -m 1

# Process with specific processor
php bin/console genaker:mq:process default -p oro_email.async.sync_email_seen_flag -m 1

# Process up to 5 messages
php bin/console genaker:mq:process -m 5

# With time limit (e.g. 1 minute)
php bin/console genaker:mq:process -m 10 -t 0:1:0
```

**Options:**

| Option | Short | Description |
|--------|-------|-------------|
| `queue` | - | Queue/destination name (argument) |
| `--processor` | `-p` | Process only with this processor (service id) |
| `--message-limit` | `-m` | Process N messages and exit (default: 1) |
| `--time-limit` | `-t` | Exit after this time (e.g. 0:0:30) |

---

## Debugging with breakpoints

1. Add breakpoints in your processor class.
2. Run:

```bash
php bin/console genaker:mq:process -m 1
```

3. Trigger a message (e.g. send an email, run a cron) so a message is queued.
4. When the command picks up the message, execution will stop at your breakpoints.

---

## Testing

```bash
composer install
php vendor/bin/phpunit -c phpunit.xml.dist
```

## Requirements

- **PHP** 8.1+
- **OroCommerce** / **Oro Platform** 6.1+
- **DBAL** message queue transport (default Oro setup)

---

## License

MIT
