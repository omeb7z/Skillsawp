<div class="entete"><div><h1>Comptes</h1><p>Désactiver un compte bloque sa connexion et annule ses demandes en attente. Ses séances acceptées restent à traiter depuis le tableau de bord.</p></div>
  <form method="get" role="search" class="recherche-entete">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Nom, prénom ou courriel" aria-label="Rechercher un compte">
    <button class="bouton petit second" type="submit"><?= icone('recherche', 16) ?> Chercher</button>
  </form></div>
<div class="panneau tableau-wrap">
  <table>
    <caption class="sr-only">Comptes étudiants et administrateurs</caption>
    <thead><tr><th>Utilisateur</th><th>Filière</th><th>Disponible / bloqué</th><th>Note</th><th>État</th><th><span class="sr-only">Actions</span></th></tr></thead>
    <tbody>
    <?php foreach ($comptes as $c): ?>
      <tr>
        <td><b><?= e($c['prenom'] . ' ' . $c['nom']) ?></b><?= $c['role'] === 'ADMIN' ? ' <span class="etat ok">Admin</span>' : '' ?><br><small><?= e($c['email']) ?></small></td>
        <td><?= e($c['filiere'] ?: '—') ?></td>
        <td><?= $c['role'] === 'ETUDIANT' ? '<b>' . (int) $c['disponible'] . ' h</b> / ' . (int) $c['bloque'] . ' h' : '—' ?></td>
        <td><?= e($c['note'] ?? '—') ?></td>
        <td><span class="etat <?= (int) $c['actif'] ? 'ok' : 'refus' ?>"><?= (int) $c['actif'] ? 'Actif' : 'Désactivé' ?></span>
          <?php if (!(int) $c['actif'] && $c['acceptees']): ?><br><small class="texte-danger"><?= (int) $c['acceptees'] ?> séance(s) à traiter</small><?php endif; ?></td>
        <td><?php if ((int) $c['id'] !== (int) $utilisateur['id']): ?>
          <form class="enligne" method="post" action="<?= e(url('/admin/comptes/' . $c['id'] . '/basculer')) ?>"><?= csrf_champ() ?>
            <button class="bouton petit <?= (int) $c['actif'] ? 'danger' : 'second' ?>" type="submit"
              <?php if ((int) $c['actif']): ?>data-confirmer="Désactiver <?= e($c['prenom'] . ' ' . $c['nom']) ?> ? Ses demandes en attente seront annulées."<?php endif; ?>>
              <?= (int) $c['actif'] ? 'Désactiver' : 'Réactiver' ?></button></form><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
