<?php
$erreurs = $erreurs ?? [];
$val = function (string $k) use ($o): string { $v = $o[$k] ?? ''; return is_scalar($v) || $v === null ? (string) $v : ''; };
?>
<div class="entete">
  <div>
    <h1><?= $id ? "Modifier l'offre" : 'Proposer une séance' ?></h1>
    <p>Un brouillon peut rester incomplet. Pour publier, tous les champs et au moins un créneau futur sont nécessaires.</p>
  </div>
</div>

<?= resume_erreurs($erreurs) ?>

<form method="post" class="cote">
  <?= csrf_champ() ?>
  <div class="panneau">
    <div class="champ">
      <label for="titre">Titre</label>
      <input id="titre" type="text" name="titre" value="<?= e($val('titre')) ?>" minlength="3" maxlength="80" required
             placeholder="Ex. : Débuter en Python avec des mini-jeux"<?= attrs_erreur($erreurs, 'titre', 'titre-erreur') ?>>
      <?= erreur_html($erreurs, 'titre', 'titre-erreur') ?>
    </div>
    <div class="champ">
      <label for="competence">Compétence</label>
      <select id="competence" name="competence_id"<?= attrs_erreur($erreurs, 'competence_id', 'competence-erreur', 'competence-aide') ?>>
        <option value="">— Choisir —</option>
        <?php foreach ($groupes as $g): ?>
          <optgroup label="<?= e($g['nom']) ?>">
            <?php foreach ($g['competences'] as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $val('competence_id') === (string) $c['id'] ? 'selected' : '' ?>><?= e($c['nom']) ?></option><?php endforeach; ?>
          </optgroup>
        <?php endforeach; ?>
      </select>
      <div class="aide" id="competence-aide">La catégorie se déduit de la compétence.</div>
      <?= erreur_html($erreurs, 'competence_id', 'competence-erreur') ?>
    </div>
    <div class="champ">
      <label for="description">Description</label>
      <textarea id="description" name="description" maxlength="1500" rows="6"<?= attrs_erreur($erreurs, 'description', 'description-erreur', 'description-aide') ?>
        placeholder="Ce que l’on fera pendant la séance, ce qu’il faut apporter, prérequis…"><?= e($val('description')) ?></textarea>
      <div class="aide" id="description-aide">20 caractères minimum pour publier. Un brouillon peut rester plus court.</div>
      <?= erreur_html($erreurs, 'description', 'description-erreur') ?>
    </div>

    <?php foreach ([['niveau', 'Niveau visé', Libelles::NIVEAUX],
                    ['duree', 'Durée de chaque séance', ['1' => '1 heure · coûte 1 crédit', '2' => '2 heures · coûte 2 crédits']],
                    ['mode', 'Mode de déroulement', Libelles::MODES]] as [$champ, $legende, $options]): ?>
      <fieldset class="champ" style="border:0;padding:0"<?= attrs_erreur($erreurs, $champ, $champ . '-erreur') ?>>
        <legend style="font-weight:700;font-size:.93rem;margin-bottom:6px"><?= e($legende) ?></legend>
        <div class="choix">
          <?php foreach ($options as $k => $l): ?>
            <label><input type="radio" name="<?= $champ ?>" value="<?= e($k) ?>" <?= $val($champ) === (string) $k ? 'checked' : '' ?>><span><?= e($l) ?></span></label>
          <?php endforeach; ?>
        </div>
        <?= erreur_html($erreurs, $champ, $champ . '-erreur') ?>
      </fieldset>
    <?php endforeach; ?>

    <div class="actions">
      <button class="bouton" type="submit">Enregistrer</button>
      <?php if (!$id || $val('etat') === 'BROUILLON'): ?><span class="discret">Vous ajouterez ensuite vos créneaux sur la fiche de l’offre.</span><?php endif; ?>
      <?php if ($id): ?><a class="bouton second" href="<?= e(url('/offres/' . $id)) ?>">Annuler</a><?php endif; ?>
    </div>
  </div>
  <aside class="panneau douce">
    <h3><?= icone('etincelle', 18) ?> Comment ça marche</h3>
    <ol style="padding-left:18px;margin:0">
      <li>Décrivez la séance et enregistrez-la en brouillon.</li>
      <li>Ajoutez des créneaux datés (lieu ou lien de réunion).</li>
      <li>Publiez : l’offre apparaît au catalogue et dans les suggestions.</li>
      <li>Acceptez ou refusez les demandes ; à la fin de la séance, chacun confirme et vous recevez vos heures.</li>
    </ol>
  </aside>
</form>
