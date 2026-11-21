import { Controller } from "@hotwired/stimulus"

// お知らせを 3 秒で消す
export default class extends Controller {
  connect() {
    this.timer = setTimeout(() => {
      this.element.classList.add("opacity-0")
      setTimeout(() => this.element.remove(), 300)
    }, 3000)
  }

  disconnect() {
    clearTimeout(this.timer)
  }
}
