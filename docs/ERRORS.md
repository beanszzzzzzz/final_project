# API Error Responses

This document describes the standardized JSON error envelope returned by the API for error conditions (validation failures, auth errors, exceptions).

Envelope

All API error responses return a JSON object with the following top-level keys:

- `success`: `false` — indicates failure.
- `message`: human-friendly message summarizing the error.
- `errors`: optional array of detailed errors (present for validation or field-level failures).
- `timestamp`: ISO 8601 timestamp when the response was produced.

Validation errors (422)

When validation fails the API returns `422 Unprocessable Entity` and an `errors` array. Each item contains:

- `property`: the property path (e.g. `email`, `address`).
- `message`: the validation message (e.g. "This value should not be blank.").
- `code`: optional machine-readable code when provided by the validator.

Example 422 response

```json
{
  "success": false,
  "message": "Validation failed.",
  "errors": [
    { "property": "email", "message": "This value should not be blank.", "code": "c1" },
    { "property": "phone", "message": "This value is too short.", "code": "c2" }
  ],
  "timestamp": "2026-05-16T12:34:56+00:00"
}
```

Auth and permission errors

- `401 Unauthorized` responses use the same envelope with a relevant message (no `errors` array).
- `403 Forbidden` responses are returned when the authenticated user lacks required permissions.

Example 401 response

```json
{
  "success": false,
  "message": "Authentication required.",
  "errors": null,
  "timestamp": "2026-05-16T12:35:00+00:00"
}
```

Client guidance

- On `422`, surface field errors inline (forms) and highlight corresponding inputs using `property`.
- Retry logic: do not retry `422` automatically; prompt user to fix input. For `5xx` consider retrying with backoff.

Where this comes from

The `src/EventSubscriber/ApiExceptionSubscriber.php` formats exceptions and validator violations into this envelope.
