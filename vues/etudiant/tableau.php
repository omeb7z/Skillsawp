<?php
$solde = (new ServiceComptes(App::db()))->portefeuille((int) $utilisateur['id']) ?? ['disponible' => 0, 'bloque' => 0];
$onboarding = $onboarding ?? null;
?>
<section class="bienvenue" aria-labelledby="titre-bienvenue">
  <div>
    <span class="badge-doux" style="background:rgba(255,255,255,.08);color:var(--barre-encre)"><?= icone('accueil', 15) ?> Votre espace</span>
    <h1 id="titre-bienvenue">Bonjour <?= e($utilisateur['prenom']) ?>, prêt à échanger du temps ?</h1>
    <p>Vous avez <?= (int) $solde['disponible'] ?> heure(s) disponible(s)<?php if ((int) $solde['bloque']): ?> et <?= (int) $solde['bloque'] ?> h réservée(s) pour des séances à venir<?php endif; ?>. Chaque heure transmise vous en fait gagner une.</p>
    <div class="actions">
      <a class="bouton" href="<?= e(url('/catalogue')) ?>"><?= icone('recherche', 18) ?> Trouver une séance</a>
      <a class="bouton second" href="<?= e(url('/offres/nouvelle')) ?>"><?= icone('plus', 18) ?> Proposer une séance</a>
    </div>
  </div>
  <div class="bienvenue-solde">
    <small>Temps disponible</small>
    <strong><?= (int) $solde['disponible'] ?><span> h</span></strong>
    <small><?= (int) $solde['bloque'] ?> h réservées</small>
  </div>
</section>

<?php if ($onboarding && !$onboarding['termine']): ?>
  <section class="panneau onboarding" data-onboarding="<?= (int) $utilisateur['id'] ?>" aria-labelledby="titre-onboarding" style="margin-top:18px">
    <div class="onboarding-entete">
      <div>
        <span class="badge-doux"><?= icone('etincelle', 15) ?> Premiers pas</span>
        <h2 id="titre-onboarding">Vos premiers pas sur SkillSwap</h2>
        <p><?= (int) $onboarding['faites'] ?> étape(s) sur <?= (int) $onboarding['total'] ?> déjà terminée(s). Prenez quelques minutes pour préparer votre premier échange.</p>
      </div>
      <button class="bouton ghost petit onboarding-fermer" type="button" data-fermer-onboarding hidden>Masquer ce guide</button>
    </div>
    <div class="onboarding-progression">
      <progress value="<?= (int) $onboarding['faites'] ?>" max="<?= (int) $onboarding['total'] ?>" aria-label="Progression du guide : <?= (int) $onboarding['faites'] ?> étapes sur <?= (int) $onboarding['total'] ?>"></progress>
      <span><?= (int) round($onboarding['faites'] / max(1, $onboarding['total']) * 100) ?> %</span>
    </div>
    <ol class="onboarding-etapes">
      <?php foreach ($onboarding['etapes'] as $index => $etape): ?>
        <li class="onboarding-etape<?= $etape['fait'] ? ' faite' : '' ?>">
          <span class="onboarding-numero" aria-hidden="true"><?= $etape['fait'] ? '✓' : $index + 1 ?></span>
          <div>
            <b><?= e($etape['titre']) ?></b>
            <p><?= e($etape['texte']) ?></p>
            <?php if ($etape['fait']): ?>
              <span class="badge-doux"><?= icone('check', 14) ?> Terminé</span>
            <?php else: ?>
              <a href="<?= e(url($etape['lien'])) ?>"><?= e($etape['action']) ?> <?= icone('fleche', 14) ?></a>
            <?php endif; ?>
          </div>
        </li>
      <?php endforeach; ?>
    </ol>
  </section>
<?php endif; ?>

<?php if ($aConfirmer): ?>
  <section class="panneau accent" style="margin-top:18px" aria-labelledby="titre-a-confirmer">
    <div class="entete" style="margin-bottom:12px">
      <div>
        <h2 id="titre-a-confirmer" style="margin:0">Séances à confirmer</h2>
        <p>Confirmez la réalisation pour que les heures soient transférées.</p>
      </div>
      <span class="badge-doux"><?= count($aConfirmer) ?> en attente</span>
    </div>
    <?php foreach ($aConfirmer as $s): ?>
      <a class="seance" style="--cat: <?= e($s['couleur']) ?>" href="<?= e(url('/reservations/' . $s['id'])) ?>">
        <span class="horaire"><?= e(heure($s['debut'])) ?><small><?= e(date_longue($s['debut'])) ?></small></span>
        <span class="seance-infos"><b><?= e($s['titre']) ?></b><br>
          <span class="discret">avec <?= e($s['autre']) ?> · <?= (int) $s['nb_confirmations'] ?>/2 confirmation(s)</span></span>
        <span class="etat ok">Confirmer</span>
      </a>
    <?php endforeach; ?>
  </section>
<?php endif; ?>

<div class="cote" style="margin-top:18px">
  <div>
    <section class="panneau" aria-labelledby="titre-prochaines">
      <div class="entete" style="margin-bottom:12px">
        <h2 id="titre-prochaines" style="margin:0">Prochaines séances</h2>
        <a href="<?= e(url('/planning')) ?>">Tout le planning <?= icone('fleche', 15) ?></a>
      </div>
      <?php foreach ($prochaines as $s): ?>
        <a class="seance" style="--cat: <?= e($s['couleur']) ?>" href="<?= e(url('/reservations/' . $s['id'])) ?>">
          <span class="horaire"><?= e(heure($s['debut'])) ?><small><?= e(date_longue($s['debut'])) ?></small></span>
          <span class="seance-infos"><b><?= e($s['titre']) ?></b><br><span class="discret">avec <?= e($s['autre']) ?></span></span>
          <span class="role <?= e($s['role']) ?>"><?= $s['role'] === 'ANIME' ? "J'anime" : "J'apprends" ?></span>
        </a>
      <?php endforeach; ?>
      <?php if (!$prochaines): ?>
        <div class="vide">
          <span aria-hidden="true"><?= icone('calendrier', 32) ?></span>
          <p>Aucune séance prévue pour le moment.</p>
          <a class="bouton petit" href="<?= e(url('/suggestions')) ?>">Voir les séances pour moi</a>
        </div>
      <?php endif; ?>
    </section>

    <section class="panneau" aria-labelledby="titre-suggestions">
      <div class="entete" style="margin-bottom:12px">
        <h2 id="titre-suggestions" style="margin:0">Suggérées pour vous</h2>
        <a href="<?= e(url('/suggestions')) ?>">Toutes les suggestions <?= icone('fleche', 15) ?></a>
      </div>
      <?php if ($suggestions): ?>
        <div class="grille g-auto"><?php foreach ($suggestions as $o) echo carte_offre($o, $o['raison'], $o['exacte']); ?></div>
      <?php elseif (!$recherchees): ?>
        <div class="vide">
          <span aria-hidden="true"><?= icone('etincelle', 32) ?></span>
          <p>Indiquez ce que vous voulez apprendre pour recevoir des suggestions.</p>
          <a class="bouton petit" href="<?= e(url('/profil')) ?>#recherchees">Choisir mes compétences recherchées</a>
        </div>
      <?php else: ?>
        <div class="vide">
          <span aria-hidden="true"><?= icone('recherche', 32) ?></span>
          <p>Aucune offre ne correspond encore à vos recherches. Revenez bientôt ou explorez le catalogue.</p>
          <a class="bouton petit second" href="<?= e(url('/catalogue')) ?>">Explorer le catalogue</a>
        </div>
      <?php endif; ?>
    </section>
  </div>

  <aside>
    <section class="panneau" aria-labelledby="titre-demandes-attente">
      <h3 id="titre-demandes-attente">Demandes à traiter</h3>
      <?php foreach (array_slice($enAttente, 0, 5) as $d): ?>
        <p style="margin:0 0 10px"><a href="<?= e(url('/reservations/' . $d['id'])) ?>"><b><?= e($d['autre']) ?></b></a>
          souhaite « <?= e($d['titre']) ?> »<br><span class="discret"><?= e(date_longue($d['debut'])) ?> à <?= e(heure($d['debut'])) ?></span></p>
      <?php endforeach; ?>
      <?php if (!$enAttente): ?><p class="discret" style="margin:0">Aucune demande en attente.</p><?php endif; ?>
      <?php if (count($enAttente) > 5): ?><a href="<?= e(url('/demandes')) ?>">Voir les <?= count($enAttente) ?> demandes <?= icone('fleche', 15) ?></a><?php endif; ?>
    </section>
    <section class="panneau" aria-labelledby="titre-reputation">
      <h3 id="titre-reputation">Ma réputation</h3>
      <p style="margin:0"><?= etoiles($note[0]) ?> <span class="discret">(<?= $note[1] ?> avis)</span></p>
      <p style="margin:10px 0 0"><a href="<?= e(url('/profil/' . $utilisateur['id'])) ?>">Voir mon profil public <?= icone('fleche', 15) ?></a></p>
    </section>
  </aside>
</div>
