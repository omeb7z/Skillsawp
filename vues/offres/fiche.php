<?php $estAdmin = $utilisateur['role'] === 'ADMIN'; ?>
<p><a class="retour" href="<?= e(url($proprietaire ? '/mes-offres' : '/catalogue')) ?>"><?= icone('fleche', 15) ?> <?= $proprietaire ? 'Mes offres' : 'Catalogue' ?></a></p>

<?php if ((int) $o['masquee']): ?>
  <div class="message erreur" style="margin-bottom:16px"><span class="message-icone" aria-hidden="true"><?= icone('alerte', 20) ?></span>Offre masquée par la modération : <?= e($o['motif_masquage']) ?></div>
<?php endif; ?>

<div class="cote">
  <div>
    <article class="panneau" style="border-top:6px solid <?= e($o['couleur'] ?: 'var(--primaire)') ?>">
      <div class="entete" style="margin-bottom:10px">
        <span class="categorie" style="--cat: <?= e($o['couleur']) ?>"><?= e($o['categorie'] ?: 'Sans catégorie') ?><?= $o['competence'] ? ' · ' . e($o['competence']) : '' ?></span>
        <?php if ($proprietaire || $estAdmin) echo etat_offre($o); ?>
      </div>
      <h1><?= e($o['titre']) ?></h1>
      <div class="etiquettes" style="margin-bottom:14px">
        <?php if ($o['niveau']): ?><span class="etiquette"><?= e(Libelles::NIVEAUX[$o['niveau']] ?? $o['niveau']) ?></span><?php endif; ?>
        <?php if ($o['mode']): ?><span class="etiquette"><?= e(Libelles::MODES[$o['mode']] ?? $o['mode']) ?></span><?php endif; ?>
        <?php if ($o['duree']) echo jeton($o['duree']); ?>
      </div>
      <p style="white-space:pre-line"><?= e($o['description'] ?: 'Pas encore de description.') ?></p>
    </article>

    <section class="panneau" id="creneaux">
      <h2>Créneaux</h2>
      <?php if (!$proprietaire): ?>
        <p class="discret">Le lieu exact ou le lien de réunion est communiqué aux seuls participants, une fois la séance acceptée.</p>
        <?php if ($solde && $o['duree'] && $solde['disponible'] < $o['duree']): ?>
          <p class="message info">Cette séance coûte <?= (int) $o['duree'] ?> h et vous avez <?= (int) $solde['disponible'] ?> h disponible(s). Animez une séance pour gagner des heures.</p>
        <?php endif; ?>
      <?php endif; ?>
      <?php $affiches = 0; foreach ($creneaux as $c): if (!$proprietaire && $c['debut'] <= $maintenant) continue; $affiches++; ?>
        <div style="display:flex;gap:14px;align-items:center;flex-wrap:wrap;padding:12px 0;border-bottom:1px solid var(--trait)">
          <div style="flex:1;min-width:200px">
            <b><?= e(date_longue($c['debut'])) ?></b>, <?= e(heure($c['debut'])) ?> – <?= e(heure($c['fin'])) ?>
            <?php if ($proprietaire): ?><br><small><?= e($c['lieu'] ?: $c['lien']) ?></small><?php endif; ?>
          </div>
          <?php if ($c['attribue']): ?>
            <span class="etat ok">Attribué</span>
            <?php if ($proprietaire): ?><a class="bouton petit second" href="<?= e(url('/reservations/' . $c['attribue'])) ?>">Voir la séance</a><?php endif; ?>
          <?php elseif ($c['debut'] <= $maintenant): ?>
            <span class="etat neutre">Passé</span>
          <?php elseif ($proprietaire): ?>
            <?php if ($c['en_attente']): ?><a class="etat attente" href="<?= e(url('/demandes')) ?>"><?= (int) $c['en_attente'] ?> demande(s)</a><?php else: ?><span class="etat neutre">Libre</span><?php endif; ?>
            <form class="enligne" method="post" action="<?= e(url('/creneaux/' . $c['id'] . '/supprimer')) ?>"><?= csrf_champ() ?>
              <button class="bouton petit danger" type="submit" data-confirmer="Retirer ce créneau ? Les demandes en attente seront annulées.">Retirer</button></form>
          <?php elseif (isset($mesDemandes[(int) $c['id']])): $md = $mesDemandes[(int) $c['id']]; ?>
            <?= etat_resa($md['etat']) ?>
            <a class="bouton petit second" href="<?= e(url('/reservations/' . $md['id'])) ?>">Suivre ma demande</a>
          <?php elseif ($utilisateur['role'] === 'ETUDIANT' && $visible): ?>
            <details class="tiroir" style="flex-basis:100%">
              <summary class="bouton petit">Demander ce créneau (<?= (int) $o['duree'] ?> h)</summary>
              <form method="post" action="<?= e(url('/creneaux/' . $c['id'] . '/demander')) ?>"><?= csrf_champ() ?>
                <div class="champ"><label for="msg<?= (int) $c['id'] ?>">Message à <?= e(explode(' ', $o['intervenant'])[0]) ?> <span class="discret">(facultatif)</span></label>
                  <textarea id="msg<?= (int) $c['id'] ?>" name="message" maxlength="300" placeholder="Votre niveau, ce que vous aimeriez travailler…"></textarea></div>
                <button class="bouton" type="submit">Envoyer la demande</button>
                <span class="discret">Rien n'est débité tant que la demande n'est pas acceptée.</span>
              </form>
            </details>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
      <?php if (!$affiches): ?><p class="discret">Aucun créneau à venir.</p><?php endif; ?>

      <?php if ($proprietaire && $o['etat'] !== 'ARCHIVEE'): ?>
        <details class="tiroir" style="margin-top:16px" <?= $creneaux ? '' : 'open' ?>>
          <summary class="bouton second">Ajouter un créneau</summary>
          <?php if ($o['duree'] && $o['mode']): ?>
          <form method="post" action="<?= e(url('/offres/' . $o['id'] . '/creneaux')) ?>" data-duree="<?= (int) $o['duree'] ?>"><?= csrf_champ() ?>
            <div class="ligne">
              <div class="champ"><label for="debut">Début</label><input id="debut" type="datetime-local" name="debut" required></div>
              <div class="champ"><label for="fin">Fin</label><input id="fin" type="datetime-local" name="fin" required>
                <div class="aide">Calculée automatiquement : <?= (int) $o['duree'] ?> h.</div></div>
            </div>
            <?php if ($o['mode'] === 'PRESENTIEL'): ?>
              <div class="champ"><label for="lieu">Lieu</label><input id="lieu" type="text" name="lieu" placeholder="Bibliothèque, salle B12…" required></div>
            <?php else: ?>
              <div class="champ"><label for="lien">Lien de réunion</label><input id="lien" type="url" name="lien" placeholder="https://meet…" required></div>
            <?php endif; ?>
            <button class="bouton" type="submit">Ajouter le créneau</button>
          </form>
          <?php else: ?><p>Choisissez d'abord la durée et le mode dans <a href="<?= e(url('/offres/' . $o['id'] . '/modifier')) ?>">l'offre</a>.</p><?php endif; ?>
        </details>
      <?php endif; ?>
    </section>
  </div>

  <aside>
    <?php if ($proprietaire): ?>
      <div class="panneau">
        <h3>Gérer l'offre</h3>
        <?php if ($o['etat'] !== 'PUBLIEE' && $manque && !(int) $o['masquee']): ?>
          <p class="discret">Pour publier, il manque : <?= e(implode(', ', $manque)) ?>.</p>
        <?php endif; ?>
        <div class="actions">
          <a class="bouton second" href="<?= e(url('/offres/' . $o['id'] . '/modifier')) ?>">Modifier</a>
          <?php if ($o['etat'] !== 'PUBLIEE' && !(int) $o['masquee']): ?>
            <form class="enligne" method="post" action="<?= e(url('/offres/' . $o['id'] . '/publier')) ?>"><?= csrf_champ() ?><button class="bouton" type="submit">Publier</button></form>
          <?php endif; ?>
          <?php if ($o['etat'] !== 'ARCHIVEE'): ?>
            <form class="enligne" method="post" action="<?= e(url('/offres/' . $o['id'] . '/archiver')) ?>"><?= csrf_champ() ?>
              <button class="bouton danger" type="submit" data-confirmer="Archiver cette offre ? Les demandes en attente seront annulées ; les séances acceptées sont maintenues.">Archiver</button></form>
          <?php endif; ?>
        </div>
      </div>
    <?php elseif ($estAdmin): ?>
      <div class="panneau">
        <h3>Modération</h3>
        <?php if ((int) $o['masquee']): ?>
          <form method="post" action="<?= e(url('/admin/offres/' . $o['id'] . '/reafficher')) ?>"><?= csrf_champ() ?><button class="bouton" type="submit">Réafficher l'offre</button></form>
        <?php else: ?>
          <form method="post" action="<?= e(url('/admin/offres/' . $o['id'] . '/masquer')) ?>"><?= csrf_champ() ?>
            <div class="champ"><label for="motif">Motif</label><textarea id="motif" name="motif" required placeholder="Contenu inapproprié, hors sujet…"></textarea></div>
            <button class="bouton danger" type="submit">Masquer l'offre</button></form>
        <?php endif; ?>
      </div>
    <?php endif; ?>
    <div class="panneau">
      <h3>Intervenant</h3>
      <div style="display:flex;gap:12px;align-items:center">
        <span class="avatar"><?= e(initiales($o['intervenant'])) ?></span>
        <div><a href="<?= e(url('/profil/' . $o['intervenant_id'])) ?>"><b><?= e($o['intervenant']) ?></b></a><br><small><?= e($o['intervenant_filiere']) ?></small></div>
      </div>
      <p style="margin:12px 0 0"><?= etoiles($note[0]) ?> <span class="discret">(<?= $note[1] ?> avis)</span></p>
      <?php foreach ($avis as $a): ?>
        <blockquote style="margin:12px 0 0;padding-left:12px;border-left:3px solid var(--accent)">
          <span class="etoiles"><?= str_repeat('★', (int) $a['note']) ?></span> <?= e($a['commentaire']) ?><br><small><?= e($a['auteur']) ?></small></blockquote>
      <?php endforeach; ?>
    </div>
  </aside>
</div>
