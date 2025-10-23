<!--
SPDX-FileCopyrightText: 2025 Markus Katharina Brechtel <markus.katharina.brechtel@thengo.net>
SPDX-License-Identifier: AGPL-3.0-or-later
-->

HACKATHON PROPOSAL

# Git, Git-Annex and Datalad Repositories for Nextcloud

This repository is about an effort to create a tight integration between Nextcloud and Repositories like Git, Git-Annex and Datalad repos at the [distribits 2025](https://www.distribits.live/events/2025-distribits/) hackathon.

After talking with several people on  I came to the conclusion that it still makes sense to push some effort into this and thought about the approach a bit today.

The idea behind this is to have a seamless integration for "normie" Nextcloud users into accessing datalad repos and editing documents inside Git and Git-Annex repos, so we can bring users and developers together with less technological gaps.

## User stories

As a project manager, I want to be able to collaborate with more technical users in a project without a lot of media gaps, so that we can work together seamlessly regardless of technical skill level.

As a data manager, I want to clone repositories to my data processing environments and work within a version-controlled repository, so that I can easily share results with other project members while maintaining control over access to sensitive files and making certain data unavailable to specific users when needed.

As a developer, I want to be able to plug in integrations and develop special CI pipelines without needing to integrate everything directly into Nextcloud, while enabling non-technical users to edit the documentation and other files based on my project easily and let them create merge requests, so that I can use standard Git workflows and tooling while still collaborating effectively with non-technical team members.

As an infrastructure provider, I want control over data storage locations and the ability to store large datasets on different servers based on easily manageable policies, so that I can leverage Git-annex features to efficiently manage storage infrastructure while providing seamless access to users.

As a translator, I want to use a comprehensive translation tracking system used in software development projects, like [Weblate](https://weblate.org), without needing to keep multiple documents in sync when there have been changes, so that I can work efficiently with version-controlled translation workflows while collaborating with non-technical project members through Nextcloud.

## Why not just use a special remote like rclone

It is true that you can currently already sync your files in the Nextcloud with special remotes like rclone.

However, this approach has significant limitations:

- **Missing version history integration**: File changes history from the Git/Datalad repository is not visible in the Nextcloud interface, making it difficult for non-technical users to understand what happened to files
- **No contextual information**: Users can't see commit messages, authors, or the reasoning behind changes directly in Nextcloud
- **Missing user attribution in Git history**: When using basic special remotes, we can't see which Nextcloud users caused the changes in the Git(-Annex) history (though there might be special remotes protocol functionality for this according to Joey)
- **Lack of Git-aware workflows**: Simple remote sync tools don't understand Git semantics like branches, merges, or the distinction between annexed and regular files
- **No bidirectional workflow support**: Non-technical users editing files in Nextcloud can't easily contribute back to the repository with proper version control

## Implementation idea

The current implementation idea is to create a twofold integration with different components:

### Component 1: Nextcloud App

Create a Nextcloud App called "Nextcloud Repositories" as a fork of the existing Team Folders App (formerly called Group Folders) where people can interact with Git repos directly in the Nextcloud Files App. When people save a file it creates a Git commit. If the file is an Annex file it is provided directly by the Nextcloud via the Git-annex special remote over the Nextclouds WebDAV interface.

In the file details we provide additional information about special remote storage location and the Git history of the file.

### Component 2: Git-annex special remote

Create a Git-annex special remote that uses the existing Nextclouds Oauth authentication to make it easy for users to sync with the Nextcloud with just a command and accepting the Nextclouds confirmation dialog, like with the Nextcloud sync app.

## Development

For the early development our primary environment target is the latest Nextcloud stable release packaged into a Podman container. The source code is mounted into the container to be able to live edit the app code and see the results directly.

The special remote is developed inside the ./git-annex-special-remote folder in Go?!…
