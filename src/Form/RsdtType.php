<?php

namespace App\Form;

use App\Entity\Rsdt;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * 住居の編集フォーム（主キー以外の 17 列）
 *
 * 項目と決まりは Rsdt::ATTRIBUTE_RULES から作る。データは配列（列名 → 値）で、エンティティには直接つながない
 * （入力チェックに通ったときだけ、UpdateRsdt がエンティティを書き換えるため）。
 * TextType は最初から、前後の空白を取り（trim）、空文字を null にする。
 * 日付は文字のまま受け取り、Assert\Date で YYYY-MM-DD と無い日付（2026-02-30 など）をはじく。
 *
 * @extends AbstractType<array<string, string|null>>
 */
final class RsdtType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        foreach (Rsdt::ATTRIBUTE_RULES as $rule) {
            $isDate = 'date' === ($rule['type'] ?? null);
            $required = $rule['required'] ?? false;
            $constraints = match (true) {
                $isDate => [new Assert\Date(message: '日付の形式が正しくありません（YYYY-MM-DD）')],
                // Length は文字数（コードポイント単位）で数える。MySQL の VARCHAR と同じ
                default => [new Assert\Length(max: $rule['max'] ?? null, maxMessage: '{{ limit }} 文字以内で入力してください')],
            };
            if ($required) {
                $constraints[] = new Assert\NotBlank(message: '必須です');
            }

            $builder->add($rule['name'], TextType::class, [
                'required' => $required,
                'constraints' => $constraints,
                // 画面では <input type="date"> にする（templates/rsdt/edit.html.twig）
                'attr' => $isDate ? [] : ['maxlength' => $rule['max'] ?? null],
            ]);
        }
    }
}
