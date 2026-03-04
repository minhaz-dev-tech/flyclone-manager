import { createInertiaApp } from '@inertiajs/react';
import { render } from 'react-dom';
import React from 'react';

createInertiaApp({
  resolve: name => import(`./Pages/${name}`),
  setup({ el, App, props }) {
    render(<App {...props} />, el);
  },
});