# Client directives

Optional, and unrelated to type safety: the `Client` passed to every handler is a side channel for
telling a single-page app what to do alongside the data. The short version lives in the
[README](../README.md); this is the full picture.

- [The side channel](#the-side-channel)
- [The payload](#the-payload)
- [Failures carry no directives](#failures-carry-no-directives)
- [Why the envelope says `unknown`](#why-the-envelope-says-unknown)
- [Reading it on the frontend](#reading-it-on-the-frontend)

## The side channel

```php
public function create(array $input, mixed $context, Client $client): array
{
    $client->success('Saved');
    $client->redirect('/docs/123', reload: true);
    $client->invalidate('users', '123');

    return ['id' => '123'];
}
```

The full interface is `redirect()`, `invalidate()`, `toast()`, and one shorthand per toast type —
`success()`, `error()`, `warning()`, `alert()` and `info()`.

This is the one place the library ships a specific implementation of an extension point rather than
a contract. `OperationSPAClient` is meant to be picked when the request carries
`X-Client-Id: operations-spa` — exactly that header, exactly that value — and any other request gets
a `NullClient` whose every method is a no-op, so handlers never need to know which kind is on the
other end, and nothing warns when a directive goes nowhere. Choosing between them is the transport's
job; the core never inspects a request. The [Laravel adapter](laravel.md#requests) delegates that
choice to a `ClientFactory`, defaulting to exactly this header rule.

## The payload

Under `operations-spa` those calls land in a `__client` key next to the data, on a **successful**
response:

```json
{
  "success": true,
  "data": {"id": "123"},
  "__client": {
    "redirect": {"url": "/docs/123", "reload": true},
    "toasts": [{"type": "success", "message": "Saved"}],
    "invalidations": [["users", "123"]],
    "type": "operations-spa"
  }
}
```

Keys are only present when something called for them. `RpcSuccess::jsonSerialize()` puts the payload
on the response by asking the client for it — `SerializableClient::serializeToArray()` — so a
transport that serializes the result gets this for free, and one that builds its own body calls the
same method.

## Failures carry no directives

Including the ones queued before the failure: a toast for work that was rolled back is worse than no
toast, so `RpcError` holds no client and there is nothing to serialize. Tell the user about a failure
from [the error branch](errors.md) the generated union already gives you.

## Why the envelope says `unknown`

**The envelope names `__client` but declares it `unknown`, on purpose.** The key is the library's —
`RpcSuccess::jsonSerialize()` writes it, so `lib/types.ts` says it may be there. The *shape* is not:
`Client` is an extension point, and your own implementation may define an entirely different set of
directives under a different schema, so neither `lib/types.ts` nor the transport interface commits to
one it cannot know. The payload travels through the transport untouched; what is withheld is only
the claim about what it is.

## Reading it on the frontend

`OperationSPAClient` is the subset this library deems useful and ships, and it gets its own file.
`lib/client-operations-spa.ts` declares `OperationsClientPayload` — the same schema
`serializeToArray()` emits — and one guard that puts it on a result:

```typescript
import {containsOperationSpaPayload} from './operations/lib/client-operations-spa';

const result = await create({name: 'Leo'});

if (containsOperationSpaPayload(result)) {
    for (const toast of result.__client.toasts ?? []) { … }   // ClientToast, fully typed
    result.__client.redirect?.url;
}
```

The check is the discriminator alone. The payload is assembled in one pass, so a server that wrote
`type: "operations-spa"` wrote the rest of it to the same schema, and unknown keys are ignored either
way — adding a directive stays backwards compatible.

The file is emitted by [`EmitOperationsSpaClient`](typescript-client.md#generators), on by default.
Drop it with `--without operations-spa` and nothing else changes; write your own guard against your
own directives, which is the same "no dishonest types" rule that makes the generator throw rather
than emit a placeholder.
