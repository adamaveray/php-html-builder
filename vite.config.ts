import { makeOxfmtConfig, makeOxlintConfig } from '@averay/codeformat';
import { defineConfig } from 'vite-plus';

export default defineConfig({
  staged: {
    '**/*.{js,ts,mts,yml,yaml,json,php}': 'vp exec codeformat check',
  },
  fmt: makeOxfmtConfig(),
  lint: makeOxlintConfig(),
});
