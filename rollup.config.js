/**
 * SPDX-FileCopyrightText: 2025 Markus Katharina Brechtel <markus.katharina.brechtel@thengo.net>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import alias from '@rollup/plugin-alias';
import babel from '@rollup/plugin-babel';
import commonjs from '@rollup/plugin-commonjs';
import replace from '@rollup/plugin-replace';
import resolve from '@rollup/plugin-node-resolve';
import terser from '@rollup/plugin-terser';
import typescript from '@rollup/plugin-typescript';
import postcss from 'rollup-plugin-postcss';
import vue from 'rollup-plugin-vue';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const isProd = process.env.NODE_ENV === 'production';

const baseConfig = {
	external: [
		// Nextcloud globals
		'@nextcloud/auth',
		'@nextcloud/axios',
		'@nextcloud/dialogs',
		'@nextcloud/event-bus',
		'@nextcloud/files',
		'@nextcloud/initial-state',
		'@nextcloud/l10n',
		'@nextcloud/logger',
		'@nextcloud/password-confirmation',
		'@nextcloud/router',
		'@nextcloud/vue',
	],
	plugins: [
		replace({
			'process.env.NODE_ENV': JSON.stringify(process.env.NODE_ENV || 'development'),
			preventAssignment: true,
		}),
		alias({
			entries: [
				{ find: '@', replacement: path.resolve(__dirname, 'src') },
			],
		}),
		vue({
			css: false,
			template: {
				isProduction: isProd,
			},
		}),
		resolve({
			extensions: ['.js', '.jsx', '.ts', '.tsx', '.vue'],
			browser: true,
			preferBuiltins: false,
		}),
		commonjs(),
		typescript({
			tsconfig: './tsconfig.json',
			sourceMap: !isProd,
		}),
		babel({
			babelHelpers: 'bundled',
			extensions: ['.js', '.jsx', '.ts', '.tsx', '.vue'],
			exclude: ['node_modules/**', '**/*.css', '**/*.scss', '**/*?rollup-plugin-vue*'],
			presets: [
				'@babel/preset-react',
			],
		}),
		postcss({
			extract: false,
			inject: true,
			minimize: isProd,
		}),
		// Handle ?raw imports (like SVG files as strings)
		{
			name: 'raw',
			resolveId(id, importer) {
				if (id.includes('?raw')) {
					const cleanId = id.split('?')[0];
					// Resolve relative paths
					if (cleanId.startsWith('.')) {
						const resolvedPath = path.resolve(path.dirname(importer), cleanId);
						return resolvedPath + '?raw';
					}
					return id;
				}
				return null;
			},
			async load(id) {
				if (id.includes('?raw')) {
					const filePath = id.split('?')[0];
					const fs = await import('fs/promises');
					const content = await fs.readFile(filePath, 'utf-8');
					return `export default ${JSON.stringify(content)}`;
				}
				return null;
			},
		},
		isProd && terser({
			format: {
				comments: false,
			},
		}),
	].filter(Boolean),
	output: {
		dir: 'js',
		format: 'esm',
		sourcemap: !isProd,
		chunkFileNames: 'repos-[name]-[hash].js',
		entryFileNames: 'repos-[name].js',
		assetFileNames: 'repos-[name][extname]',
	},
};

export default [
	{
		...baseConfig,
		input: {
			init: './src/init.ts',
			files: './src/files.js',
			sharing: './src/SharingSidebarApp.js',
		},
	},
];
