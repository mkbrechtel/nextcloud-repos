// SPDX-FileCopyrightText: 2026 Mirian Brechtel <brechtel@med.uni-frankfurt.de>
// SPDX-License-Identifier: AGPL-3.0-or-later
import { defineConfig } from 'astro/config';
import starlight from '@astrojs/starlight';

export default defineConfig({
	site: 'https://nextcloud-repos-website-demo.devio.mkbrechtel.dev',
	integrations: [
		starlight({
			title: 'Nextcloud Repositories',
			description: 'Git, Git-Annex and Datalad repositories inside Nextcloud — one URL, one credential, one history.',
			social: [
				{ icon: 'codeberg', label: 'Codeberg', href: 'https://codeberg.org/mkbrechtel/nextcloud-repos' },
				{ icon: 'github', label: 'GitHub', href: 'https://github.com/mkbrechtel/nextcloud-repos' },
			],
			sidebar: [
				{ label: 'Start here', items: [
					{ label: 'What is this?', slug: 'index' },
					{ label: 'Live demo', slug: 'demo' },
					{ label: 'Getting started', slug: 'getting-started' },
				]},
				{ label: 'Understand', items: [
					{ label: 'How it works', slug: 'how-it-works' },
					{ label: 'Architecture decisions', slug: 'architecture' },
				]},
				{ label: 'Contribute', items: [
					{ label: 'Development', slug: 'development' },
				]},
			],
		}),
	],
});
