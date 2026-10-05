import eslint from '@eslint/js';
import reactHooks from 'eslint-plugin-react-hooks';
import globals from 'globals';
import tseslint from 'typescript-eslint';

export default tseslint.config(
    {
        ignores: [
            'node_modules/**',
            'public/**',
            'storage/**',
            'vendor/**',
        ],
    },
    eslint.configs.recommended,
    ...tseslint.configs.recommended,
    {
        files: ['resources/js/**/*.{ts,tsx}'],
        languageOptions: {
            globals: globals.browser,
        },
        plugins: {
            'react-hooks': reactHooks,
        },
        rules: {
            ...reactHooks.configs.flat.recommended.rules,
            '@typescript-eslint/no-unused-vars': [
                'error',
                {
                    argsIgnorePattern: '^_',
                    caughtErrorsIgnorePattern: '^_',
                    varsIgnorePattern: '^_',
                },
            ],
            'react-hooks/set-state-in-effect': 'off',
        },
    },
);
