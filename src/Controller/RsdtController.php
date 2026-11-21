<?php

namespace App\Controller;

use App\Form\RsdtType;
use App\Search\RsdtListQuery;
use App\UseCase\DeleteRsdt;
use App\UseCase\GetRsdt;
use App\UseCase\SearchRsdt;
use App\UseCase\UpdateRsdt;
use App\UseCase\UpdateRsdtStatus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * 画面からの操作を受け取る
 * ここでやるのは「パラメーターを読む → ユースケースを呼ぶ → 表示・redirect」だけ
 * ユースケースは、メソッドの引数に書くと Symfony が作って渡してくれる（autowiring）
 * {id} は主キー 5 列を "_" でつないだもの（例：/rsdt/131016_0001001_006_001_/edit）
 */
final class RsdtController extends AbstractController
{
    /** 一覧（検索・ページャー） */
    #[Route('/', name: 'rsdts_index', methods: ['GET'])]
    public function index(Request $request, SearchRsdt $search): Response
    {
        $query = RsdtListQuery::fromParams($request->query->all());
        $result = $search->call($query->criteria, $query->page, $query->perPage);

        return $this->render('rsdt/index.html.twig', [
            'query' => $query,
            'result' => $result,
            // 編集・削除のあとに戻ってくる URL（今の検索条件とページ）
            'back' => $this->generateUrl('rsdts_index', $query->withPage($result->page)->toParams()),
        ]);
    }

    #[Route('/rsdt/{id}/edit', name: 'rsdts_edit', methods: ['GET'])]
    public function edit(Request $request, string $id, GetRsdt $get): Response
    {
        $rsdt = $get->call($id);
        if (null === $rsdt) {
            return $this->render('rsdt/not_found.html.twig', response: new Response(status: 404));
        }
        $back = $this->safeBack($request);
        $form = $this->createEditForm($id, $back, $rsdt->formValues());

        return $this->render('rsdt/edit.html.twig', ['rsdt' => $rsdt, 'form' => $form, 'back' => $back]);
    }

    /**
     * 更新。入力チェックに通らなければ、redirect せずに編集画面をそのまま出す（422）
     * Laravel 版は「編集画面に redirect して、入力値とエラーをセッションで渡す」が、Symfony はこの形がふつう
     */
    #[Route('/rsdt/{id}', name: 'rsdts_update', methods: ['PATCH'])]
    public function update(Request $request, string $id, GetRsdt $get, UpdateRsdt $update): Response
    {
        $back = $this->safeBack($request);
        $rsdt = $get->call($id);
        if (null === $rsdt) {
            $this->addFlash('alert', 'この行はすでに削除されています');

            return $this->redirect($back);
        }

        $form = $this->createEditForm($id, $back, $rsdt->formValues());
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->render('rsdt/edit.html.twig', ['rsdt' => $rsdt, 'form' => $form, 'back' => $back], new Response(status: 422));
        }

        return match ($update->call($id, $form->getData())) {
            UpdateRsdtStatus::Updated => $this->redirectWithFlash($back, 'notice', '更新しました'),
            UpdateRsdtStatus::NotFound => $this->redirectWithFlash($back, 'alert', 'この行はすでに削除されています'),
        };
    }

    #[Route('/rsdt/{id}', name: 'rsdts_delete', methods: ['DELETE'])]
    public function delete(Request $request, string $id, DeleteRsdt $delete): Response
    {
        $back = $this->safeBack($request);
        // 削除ボタンのフォームの CSRF トークン（templates/rsdt/_delete_button.html.twig）
        if (!$this->isCsrfTokenValid('submit', (string) $request->request->get('_csrf_token'))) {
            return $this->redirectWithFlash($back, 'alert', '画面を開き直してから、もう一度削除してください');
        }
        $delete->call($id);

        return $this->redirectWithFlash($back, 'notice', '削除しました');
    }

    /**
     * @param array<string, string> $values
     *
     * @return FormInterface<array<string, string|null>>
     */
    private function createEditForm(string $id, string $back, array $values): FormInterface
    {
        return $this->createForm(RsdtType::class, $values, [
            'method' => 'PATCH',
            'action' => $this->generateUrl('rsdts_update', ['id' => $id, 'back' => $back]),
        ]);
    }

    private function redirectWithFlash(string $url, string $type, string $message): Response
    {
        $this->addFlash($type, $message);

        return $this->redirect($url);
    }

    /** 戻り先は、このサイトの中のパスだけを許す（外のサイトへ飛ばされないように） */
    private function safeBack(Request $request): string
    {
        $back = $request->query->get('back');

        return is_string($back) && str_starts_with($back, '/') && !str_starts_with($back, '//') && !str_contains($back, '\\')
            ? $back
            : $this->generateUrl('rsdts_index');
    }
}
