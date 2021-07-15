# Nextcloud filesystem API

## High level overview

The Nextcloud filesystem is roughly based on the unix filesystem, consisting of multiple storages
mounted at various locations.

```
     ┌──────────────────────────────────┐
     │Code wanting to use the filesystem│
     └─────────┬─────────────────────┬──┘
               │                     │
               │                     │
┌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌┐
╎Filesystem    │                     │         ╎
╎layer         │new                  │legacy   ╎
╎              │                     │         ╎
╎              ▼                     ▼         ╎
╎      ┌────────┐ Partly build on  ┌─┴──────┐  ╎
╎      │Node API├─────────────────►│View API│  ╎
╎      └───────┬┘                  └─┬──────┘  ╎
╎              │                     │         ╎
└╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌┘
               │                     │
┌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌┐
╎Storage layer │                     │         ╎
╎              ├─────────────────────┤         ╎
╎              │                     │         ╎
╎              ▼                     ▼         ╎
╎        ┌───────┐    ┌───────┐    ┌──────┐    ╎
╎        │Storage│═══>│Scanner│═══>│Cache │    ╎
╎        └───────┘    └───────┘    └──────┘    ╎
╎                                              ╎
╎                                              ╎
└╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌┘
```

### Filesystem layer

Any code that wants to use the filesystem has two API options to use, the new `Node` api and the old `View` api.
New code should preferably use the `Node` api as it allows building systems with less overhead than the old api.

Besides the filesystem apis, this layer also manages the available mounts, containing the logic to allow apps
to setup their mounts and translating filesystem paths into a mountpoint + "internal" path.

### Storage layer

The storage implementation handles the details of communicating with the filesystem or remote storage api
and provide a uniform api for Nextcloud to use the storage.

For each storage a metadata cache/index is maintained to allow reading metadata of the storage without having
to talk to the (potentially) slow storage backend. The scanner is responsible for updating the cache with
information from the storage backend.

## Storage/Cache wrappers

To allow apps to customize the behaviour of a storage without requiring the app to implement this for every
possible storage backend, a `Wrapper` system is used.

A `Wrapper` encapsulates an inner storage and allows overwriting any method to customize its behavior, with
all other methods being passed through to the inner storage.

Generally search storage wrapper has an equivalent cache wrapper encapsulating the cache of the inner storage
to provide the same behavior modifications when reading metadata from the cache.

Wrappers can be layered to stack the behavior of the wrappers, for example the `groupfolders` app works by
stacking a wrapper to provide access to a single folder on the root storage with a wrapper to limit the permissions
of the storage.

```
┌───────────────┐      ┌────────────────────┐
│PermissionsMask├─────►│CachePermissionsMask│  PermissionsMask applies a mask to the permissions of a storage
└───────┬───────┘      └─────────┬──────────┘  to provide less-privilaged access to a storage
        │                        │
        ▼                        ▼
┌───────────────┐      ┌────────────────────┐
│Jail           ├─────►│CacheJail           │  Jail restricts access to a file or folder of a storage providing
└───────┬───────┘      └─────────┬──────────┘  a limited view into the storage (think unix chroot or bind mount)
        │                        │
        ▼                        ▼
┌───────────────┐      ┌────────────────────┐
│Base Storage   ├─────►│Base Cache          │
└───────────────┘      └────────────────────┘
```

## Code Map

Approximate overview of the significant filesystem code

#### AppData

High level api for accessing "appdata" folders, based on the `Node` API
  
#### Cache

- `Cache` implementation
- Cache wrappers
- Scanner and cache update logic
- Search infrastructure

#### Mount

Mountpoint management and setup

#### Node

`Node` filesystem api implementation

#### ObjectStorage

Implementation of the various supported object store storage backends

#### SimpleFS 

Simplified version of the Node api, for providing a more limited api for some filesystem bits

#### Storage

Implementation of various storage backends and wrappers

#### Streams

Various low-level php stream wrapper used in storage implementations

#### Type

Mimetype management and detection

#### View.php

Legacy View api
