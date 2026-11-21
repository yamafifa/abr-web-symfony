import { Controller } from "@hotwired/stimulus"

// 送信の前に、確認のモーダルを出す（Rails 版の confirm_controller.js と同じもの）
// - 囲んだフォームの submit を一度止めて <dialog> を開き、「確定」で送り直す
// - diff モード（data-confirm-diff-value="true"）では、変わった項目の「変更前 → 変更後」を表にして見せる
export default class extends Controller {
  static targets = ["dialog", "diff", "field", "acceptButton"]
  static values = { diff: Boolean }

  open(event) {
    // 「確定」を押したあとの送り直しは、そのまま通す
    if (this.accepted) {
      this.accepted = false
      return
    }
    event.preventDefault()
    this.form = event.target
    if (this.diffValue) this.renderDiff()
    this.acceptButtonTarget.disabled = false
    this.dialogTarget.showModal()
  }

  accept() {
    this.accepted = true
    this.acceptButtonTarget.disabled = true
    this.acceptButtonTarget.textContent = "処理中…"
    this.form.requestSubmit()
  }

  cancel() {
    this.dialogTarget.close()
  }

  // 後ろの暗い部分をクリックしたら閉じる
  backdrop(event) {
    if (event.target === this.dialogTarget) this.cancel()
  }

  renderDiff() {
    const changes = this.fieldTargets
      .map((f) => ({ column: f.dataset.column, before: f.dataset.original, after: f.value.trim() }))
      .filter((c) => c.before !== c.after)
    const box = this.diffTarget
    box.replaceChildren()
    if (changes.length === 0) {
      box.append(el("p", "", "変更された項目はありません。このまま保存しますか？"))
      return
    }
    const table = el("table", "w-full text-left text-xs")
    const head = el("tr", "text-slate-500")
    for (const h of ["列", "変更前", "変更後"]) head.append(el("th", "py-1 pr-2 font-medium", h))
    table.append(el("thead", "", head))
    const body = el("tbody", "divide-y divide-white/5 font-mono")
    for (const c of changes) {
      const tr = el("tr")
      tr.append(
        el("td", "py-1.5 pr-2 text-slate-400", c.column),
        el("td", "py-1.5 pr-2 text-slate-500 line-through", c.before || "（空）"),
        el("td", "py-1.5 text-cyan-300", c.after || "（空）"),
      )
      body.append(tr)
    }
    table.append(body)
    box.append(table)
  }
}

// 要素を作る（文字は textContent で入れるので、入力値に HTML が入っていても安全）
function el(tag, className = "", child) {
  const e = document.createElement(tag)
  if (className) e.className = className
  if (typeof child === "string") e.textContent = child
  else if (child) e.append(child)
  return e
}
