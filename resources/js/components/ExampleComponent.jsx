/* @jsxRuntime automatic @jsxImportSource react */
// Every island component starts with the line above. It switches the file
// from the editor blocks' wp.element JSX to bundled React; see vite.config.js.
import { useState } from 'react'

export default function ExampleComponent({ title = 'React island' }) {
  const [count, setCount] = useState(0)

  return (
    <div className="card">
      <div className="card-body">
        <h2 className="card-title h5">{title}</h2>
        <p className="card-text">
          Rendered by React, styled by Bootstrap.
        </p>
        <button
          type="button"
          className="btn btn-primary"
          onClick={() => setCount(count + 1)}
        >
          Clicked <span className="badge text-bg-light">{count}</span>
        </button>
      </div>
    </div>
  )
}
