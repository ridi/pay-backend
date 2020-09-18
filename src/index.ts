require('module-alias/register');

import { App } from 'src/App';

(async () => {
  const app = new App();
  await app.start();
})();
