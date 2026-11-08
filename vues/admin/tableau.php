<?php $decalage = Horloge::decalageMinutes(App::db()); ?>
<div class="entete"><div><h1>Tableau de bord</h1><p>L'état de la banque du temps du campus.</p></div></div>

<div class="kpis">
  <div class="kpi">Comptes actifs<b><?= $s['comptes_actifs'] ?></b><small><?= $s['comptes_inactifs'] ?> désactivé(s)</small></div>
  <div class="kpi">Offres publiées<b><?= $s['offres_publiees'] ?></b><small><?= $s['offres_masquees'] ?> masquée(s)</small></div>
  <div class="kpi">Séances acceptées<b><?= $s['seances_acceptees'] ?></b><small>à venir ou à confirmer</small></div>
  <div class="kpi">Séances réalisées<b><?= $s['seances_realisees'] ?></b></div>
  <div class="kpi">Heures transférées<b class="valeur-accent"><?= $s['credits_transferes'] ?> h</b><small><?= $s['credits_bloques'] ?> h bloquées</small></div>
  <div class="kpi <?= $s['litiges_ouverts'] ? 'alerte' : '' ?>">Litiges ouverts<b><?= $s['litiges_ouverts'] ?></b><small><?= $s['litiges_regles'] ?> réglé(s)</small></div>
</div>

<div class="grille g2 marge-haut">
  <div class="panneau">
    <h2>Litiges à trancher</h2>
    <div class="liste-compacte">
    <?php foreach ($litiges as $l): ?>
      <p><a href="<?= e(url('/litiges/' . $l['id'])) ?>"><b><?= e($l['titre']) ?></b></a><br>
        <small><?= e(Libelles::MOTIFS_LITIGE[$l['motif']]) ?> · ouvert le <?= e(date_courte($l['ouvert_le'])) ?></small></p>
    <?php endforeach; ?>
    </div>
    <?php if (!$litiges): ?><p class="discret">Aucun litige ouvert.</p><?php endif; ?>

    <h3 class="marge-haut">Séances liées à un compte désactivé</h3>
    <p class="discret">Elles nécessitent votre traitement : aucun crédit bloqué ne doit disparaître.</p>
    <?php foreach ($aTraiter as $r): ?>
      <details class="tiroir marge-bas-s">
        <summary><b><?= e($r['titre']) ?></b> — <?= e(date_courte($r['debut'])) ?><br>
          <small><?= e($r['intervenant']) ?><?= (int) $r['i_actif'] ? '' : ' (désactivé)' ?> → <?= e($r['beneficiaire']) ?><?= (int) $r['b_actif'] ? '' : ' (désactivé)' ?></small></summary>
        <form method="post" action="<?= e(url('/admin/reservations/' . $r['id'] . '/dossier')) ?>"><?= csrf_champ() ?>
          <label for="d<?= (int) $r['id'] ?>">Note d'ouverture</label>
          <textarea id="d<?= (int) $r['id'] ?>" name="description" required minlength="10">Compte désactivé : traitement administratif de la séance.</textarea>
          <button class="bouton petit marge-haut-s" type="submit">Ouvrir un dossier de règlement</button>
        </form>
      </details>
    <?php endforeach; ?>
    <?php if (!$aTraiter): ?><p class="discret">Rien à traiter.</p><?php endif; ?>
  </div>

  <div class="panneau">
    <h2>Séances réalisées par catégorie</h2>
    <?php $maxi = max(1, ...array_map(fn($c) => (int) $c['n'], $s['par_categorie'] ?: [['n' => 1]])); ?>
    <div class="barres">
      <?php foreach ($s['par_categorie'] as $c): ?>
        <div class="rang" style="--cat: <?= e($c['couleur']) ?>"><span><?= e($c['nom']) ?></span>
          <span class="piste"><span class="rempli" style="display:block;width:<?= round($c['n'] / $maxi * 100) ?>%"></span></span><b><?= (int) $c['n'] ?></b></div>
      <?php endforeach; ?>
    </div>
    <h3 class="marge-haut">Réservations par état</h3>
    <div class="etiquettes"><?php foreach ($s['par_etat'] as $et): ?><?= etat_resa($et['etat']) ?> <b class="espace-droite-s"><?= (int) $et['n'] ?></b><?php endforeach; ?></div>

    <h3 class="marge-haut">Contrôle d'intégrité des crédits</h3>
    <?php if ($incoherences): ?>
      <p class="message erreur"><?= count($incoherences) ?> portefeuille(s) incohérent(s) avec l'historique !</p>
    <?php else: ?>
      <p class="message succes marge-nulle">Tous les portefeuilles correspondent exactement à leur historique (<?= $s['credits_en_circulation'] ?> h en circulation).</p>
    <?php endif; ?>
  </div>
</div>

<div class="panneau marge-haut">
  <h2>Horloge de démonstration</h2>
  <p class="discret">Pour la soutenance : avancez le temps pour montrer les confirmations, sans attendre la fin réelle d'une séance. Toutes les règles temporelles utilisent cette horloge.</p>
  <form method="post" action="<?= e(url('/admin/horloge')) ?>" class="actions"><?= csrf_champ() ?>
    <?php foreach (['Heure réelle' => 0, '+3 heures' => 180, '+1 jour' => 1440, '+3 jours' => 4320, '+7 jours' => 10080] as $lib => $m): ?>
      <button class="bouton petit <?= $decalage === $m ? '' : 'second' ?>" type="submit" name="minutes" value="<?= $m ?>"><?= $lib ?></button>
    <?php endforeach; ?>
  </form>
</div>
