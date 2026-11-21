import { Controller } from "@hotwired/stimulus"

// 値を変えたら、すぐにフォームを送る（表示件数の選択で使う）
export default class extends Controller {
  submit() {
    this.element.form?.requestSubmit()
  }
}
