<?php

namespace App\Controller;

use App\Search\RsdtEsListQuery;
use App\UseCase\SearchRsdtEs;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Elasticsearch で検索する一覧（DB の一覧とは別の画面。ページは search_after で送る）
 * 編集・削除は DB 検索と同じ画面（RsdtController）を使い、戻り先をこの画面にする
 */
final class EsRsdtController extends AbstractController
{
    #[Route('/es', name: 'rsdts_es', methods: ['GET'])]
    public function index(Request $request, SearchRsdtEs $search): Response
    {
        $q = RsdtEsListQuery::fromParams($request->query->all());
        $result = $search->call($q->criteria, $q->perPage, $q->after, $q->before, $q->last);

        return $this->render('es/index.html.twig', [
            'query' => $q,
            'result' => $result,
            // 編集・削除のあとに戻ってくる URL（今の URL。同じ after・before なら同じページが出る）
            'back' => $this->generateUrl('rsdts_es', $q->toParams()),
        ]);
    }
}
