import { createElement } from 'react'
import { createRoot } from 'react-dom/client'

// Each component is its own chunk, fetched only when a page asks for it.
const components = import.meta.glob('./components/*.jsx')

/**
 * Mount a React root on every element marked up as an island:
 *
 *   <div data-react="ExampleComponent" data-props='@json(['title' => 'Hi'])'>
 *     Server-rendered fallback, replaced once React mounts.
 *   </div>
 *
 * Only these elements are handed to React. Post content and plugin output
 * elsewhere on the page is left alone.
 */
export function mountIslands(root = document) {
  for (const el of root.querySelectorAll('[data-react]')) {
    const name = el.dataset.react
    const load = components[`./components/${name}.jsx`]

    if (!load) {
      console.warn(`[islands] No component "${name}" in resources/js/components`)
      continue
    }

    let props
    try {
      props = JSON.parse(el.dataset.props || '{}')
    } catch (error) {
      console.warn(`[islands] Invalid data-props on "${name}"`, error)
      continue
    }

    whenReady(el, () =>
      load().then(module =>
        createRoot(el).render(createElement(module.default, props))
      )
    )
  }
}

/**
 * Decide when an island's component is downloaded and mounted.
 *
 * TODO: mounts eagerly for now. Swap in a strategy that suits the site.
 */
function whenReady(el, mount) {
  mount()
}
