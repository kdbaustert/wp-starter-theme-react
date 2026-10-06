import './bootstrap'

// __THEME_REACT__ is set from the features switch in vite.config.js. React is
// only downloaded on pages that contain an island.
if (__THEME_REACT__ && document.querySelector('[data-react]')) {
  import('./islands').then(({ mountIslands }) => mountIslands())
}
