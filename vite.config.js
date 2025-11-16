import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'

export default defineConfig({
	plugins: [vue()],
	build: {
		outDir: '.', // Build directly in the root, not in dist/
		emptyOutDir: false,
		cssCodeSplit: false,
		lib: {
			entry: resolve(__dirname, 'src/app.js'),
			name: 'ConversionsApp',
			fileName: () => 'js/conversions-app.js',
			formats: ['iife'],
		},
		rollupOptions: {
			output: {
				// Emit CSS to the app's css/ folder so Util::addStyle can load it
				assetFileNames: 'css/[name][extname]',
			},
		},
	},
	define: {
		'process.env.NODE_ENV': JSON.stringify('production'),
	},
	resolve: {
		alias: {
			'@': resolve(__dirname, 'src'),
		},
	},
})
