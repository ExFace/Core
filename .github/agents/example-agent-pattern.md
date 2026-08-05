---
name: example-agent-pattern
description: Kurze Beschreibung, was der Agent kann
tools: ["read", "search", "edit", "execute"]
agents: ["backend-agent", "testing-agent"]
model: GPT-5.2
user-invocable: true
disable-model-invocation: true
---

# Verhalten

Hier stehen die eigentlichen Anweisungen für den Agenten.

## Regeln / instructions

- Erst Code lesen, dann ändern.
- Keine unrelated files ändern.
- Bestehende Architektur beachten.
- Tests ausführen, wenn möglich.

// Überschriften sind frei änderbar