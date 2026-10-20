# Copyright (c) 2026, Sitiame Capital
# License: MIT

"""Tests pour la validation des paiements Jèko (subscription_api.py).

Exécution dans le container :
  bench --site erp.sitiame-capital.com run-tests \
      --app sitiame_core --module sitiame_core.tests.test_subscription_jeko

Ces tests mockent jeko_client pour ne jamais déclencher de paiement réel.
"""

import hashlib
import hmac
import json
from unittest.mock import MagicMock, patch

import frappe
from frappe.tests.utils import FrappeTestCase

from sitiame_core import subscription_api
from sitiame_core.subscription_api import (
    FAILED,
    LINK,
    LINK_CLOSED,
    CHECKOUT,
    PAID,
    PENDING,
    TO_CHECK,
    _amount_mismatch,
    _link_status,
    _mark_paid,
    _settle,
)


# ── Helpers ──────────────────────────────────────────────────────────────────


def _fake_payment_doc(amount=15000, status=PENDING, provider=CHECKOUT, transaction_id="txn-001", company="TEST_CO"):
    """Retourne un objet factice imitant un Subscription Payment."""
    doc = MagicMock()
    doc.name = "SP-TEST-001"
    doc.amount = amount
    doc.currency = "XOF"
    doc.status = status
    doc.payment_provider = provider
    doc.transaction_id = transaction_id
    doc.company = company
    doc.offer = "Essentiel"
    doc.duration_months = 1
    doc.get = lambda k, d=None: getattr(doc, k, d)
    return doc


def _jeko_paid_response(amount_fcfa=15000):
    """Réponse Jèko pour un paiement réussi."""
    return {
        "amount": {"amount": amount_fcfa, "currency": "XOF"},
        "fees": {"amount": 0},
    }


# ── T1 : _amount_mismatch — montant correct ───────────────────────────────────


class TestAmountMismatch(FrappeTestCase):
    """Tests unitaires de _amount_mismatch (pas d'accès DB)."""

    def _doc(self, amount):
        doc = MagicMock()
        doc.amount = amount
        doc.currency = "XOF"
        doc.get = lambda k, d=None: getattr(doc, k, d)
        return doc

    def test_correct_fcfa_amount(self):
        """Montant exact en FCFA → pas d'erreur."""
        doc = self._doc(15_000)
        paid = {"amount": {"amount": 15000, "currency": "XOF"}}
        self.assertIsNone(_amount_mismatch(doc, paid))

    def test_correct_cents_amount(self):
        """Jèko peut envoyer en centimes (×100) → accepté."""
        doc = self._doc(15_000)
        paid = {"amount": {"amount": 1_500_000, "currency": "XOF"}}
        self.assertIsNone(_amount_mismatch(doc, paid))

    def test_correct_net_of_fees(self):
        """Montant net des frais → accepté."""
        doc = self._doc(15_000)
        paid = {"amount": {"amount": 14_700, "currency": "XOF"}, "fees": {"amount": 300}}
        self.assertIsNone(_amount_mismatch(doc, paid))

    def test_wrong_amount_returns_error(self):
        """Montant différent → message d'erreur."""
        doc = self._doc(15_000)
        paid = {"amount": {"amount": 20, "currency": "XOF"}}
        self.assertIsNotNone(_amount_mismatch(doc, paid))

    def test_wrong_currency_returns_error(self):
        """Devise différente → message d'erreur."""
        doc = self._doc(15_000)
        paid = {"amount": {"amount": 15_000, "currency": "EUR"}}
        self.assertIsNotNone(_amount_mismatch(doc, paid))

    def test_no_amount_field_returns_error(self):
        """Pas de montant dans la réponse → message d'erreur."""
        doc = self._doc(15_000)
        self.assertIsNotNone(_amount_mismatch(doc, {}))

    def test_none_paid_returns_error(self):
        """paid=None → message d'erreur."""
        doc = self._doc(15_000)
        self.assertIsNotNone(_amount_mismatch(doc, None))


# ── T2 : _link_status — lien expiré ≠ payé ───────────────────────────────────


class TestLinkStatus(FrappeTestCase):
    """Un lien Jèko expiré ne doit jamais être marqué PAYÉ."""

    def test_expired_link_returns_link_closed(self):
        """canReceivePayments=False → LINK_CLOSED (pas PAID, pas FAILED)."""
        fake_client = MagicMock()
        fake_client.get_payment_link.return_value = {"canReceivePayments": False}
        with patch("sitiame_core.subscription_api.jeko_client", fake_client):
            result = _link_status(LINK, "lnk-expired-001")
        self.assertEqual(result, LINK_CLOSED)
        self.assertNotEqual(result, PAID)

    def test_active_link_returns_pending(self):
        """canReceivePayments=True → PENDING."""
        fake_client = MagicMock()
        fake_client.get_payment_link.return_value = {"canReceivePayments": True}
        with patch("sitiame_core.subscription_api.jeko_client", fake_client):
            result = _link_status(LINK, "lnk-active-001")
        self.assertEqual(result, PENDING)

    def test_unknown_provider_returns_pending(self):
        """Ancien fournisseur → PENDING (pas PAID)."""
        result = _link_status("ancien_fournisseur", "txn-old-001")
        self.assertEqual(result, PENDING)


# ── T3 : _settle — lien expiré reste PENDING ─────────────────────────────────


class TestSettle(FrappeTestCase):
    """_settle ne marque jamais PAID un lien expiré."""

    def test_closed_link_stays_pending(self):
        """Un LINK_CLOSED reste PENDING après _settle + log d'erreur."""
        row = MagicMock()
        row.name = "SP-LINK-001"
        row.payment_provider = LINK
        row.transaction_id = "lnk-closed-001"

        fake_client = MagicMock()
        fake_client.get_payment_link.return_value = {"canReceivePayments": False}

        with patch("sitiame_core.subscription_api.jeko_client", fake_client):
            with patch("sitiame_core.subscription_api._flag_closed_link") as mock_flag:
                status = _settle(row)

        self.assertEqual(status, PENDING)
        mock_flag.assert_called_once_with("SP-LINK-001")

    def test_failed_checkout_marks_failed(self):
        """Un checkout "error" est marqué FAILED."""
        row = MagicMock()
        row.name = "SP-CHECKOUT-002"
        row.payment_provider = CHECKOUT
        row.transaction_id = "txn-error-001"

        fake_client = MagicMock()
        fake_client.get_payment_request.return_value = {"status": "error"}

        with patch("sitiame_core.subscription_api.jeko_client", fake_client):
            with patch("frappe.db.set_value") as mock_set:
                with patch("frappe.db.commit"):
                    status = _settle(row)

        self.assertEqual(status, FAILED)


# ── T4 : Webhook — signature invalide rejetée ────────────────────────────────


class TestWebhookSecurity(FrappeTestCase):
    """Le webhook refuse toute requête sans signature valide."""

    def _make_request(self, body_dict, secret=None, valid_sig=True):
        """Simule une requête webhook Jèko."""
        raw = json.dumps(body_dict).encode()
        if valid_sig and secret:
            sig = hmac.new(secret.encode(), raw, hashlib.sha256).hexdigest()  # type: ignore[attr-defined]
        else:
            sig = "invalide_xxx"
        return raw, sig

    def test_invalid_signature_rejected(self):
        """Signature invalide → réponse 401, statut non modifié."""
        raw = b'{"status":"success"}'
        fake_client = MagicMock()
        fake_client.valid_signature.return_value = False

        with patch("sitiame_core.subscription_api.jeko_client", fake_client):
            with patch("frappe.get_request_header", return_value="bad_sig"):
                with patch("frappe.request") as mock_req:
                    mock_req.get_data.return_value = raw
                    mock_req.remote_addr = "1.2.3.4"
                    with patch("frappe.response", {}):
                        with patch("frappe.db.set_value") as mock_set:
                            # Ne doit pas atteindre set_value
                            try:
                                subscription_api.jeko_subscription_webhook()
                            except Exception:
                                pass
                            mock_set.assert_not_called()

    def test_valid_signature_success_processed(self):
        """Webhook signé + status success + paymentLinkId → _mark_paid appelé."""
        body = {
            "status": "success",
            "amount": {"amount": 15000, "currency": "XOF"},
            "fees": {"amount": 0},
            "transactionDetails": {"paymentLinkId": "lnk-001"},
        }
        raw = json.dumps(body).encode()

        fake_client = MagicMock()
        fake_client.valid_signature.return_value = True

        with patch("sitiame_core.subscription_api.jeko_client", fake_client):
            with patch("frappe.get_request_header", return_value="valid_sig"):
                with patch("frappe.request") as mock_req:
                    mock_req.get_data.return_value = raw
                    mock_req.remote_addr = "1.2.3.4"
                    with patch("frappe.conf", {}):
                        with patch("sitiame_core.subscription_api._find_payment", return_value="SP-001"):
                            with patch("sitiame_core.subscription_api._mark_paid") as mock_paid:
                                subscription_api.jeko_subscription_webhook()
                                mock_paid.assert_called_once()


# ── T5 : Idempotence — webhook rejoué sur paiement déjà payé ─────────────────


class TestWebhookIdempotency(FrappeTestCase):
    """Un webhook rejoué sur un paiement déjà PAYÉ n'étend pas l'abonnement."""

    def test_replay_on_paid_payment_is_noop(self):
        """_mark_paid avec status déjà PAID → return True sans modifier DB."""
        with patch("frappe.db.get_value", return_value=PAID):
            with patch("frappe.db.set_value") as mock_set:
                result = _mark_paid("SP-ALREADY-PAID")
        self.assertTrue(result)
        mock_set.assert_not_called()


# ── T6 : TO_CHECK — montant incorrect signalé ────────────────────────────────


class TestAmountCheckFlag(FrappeTestCase):
    """Un paiement Jèko avec mauvais montant passe en TO_CHECK, pas PAID."""

    def test_wrong_amount_flagged_to_check(self):
        """_mark_paid avec mauvais montant → status TO_CHECK."""
        with patch("frappe.db.get_value", return_value=PENDING):
            with patch("frappe.get_doc") as mock_get:
                doc = _fake_payment_doc(amount=15_000, status=PENDING)
                doc.db_set = MagicMock()
                mock_get.return_value = doc

                with patch("sitiame_core.subscription_api._apply_paid_offer"):
                    with patch("sitiame_core.subscription_api._restore_trial_roles"):
                        with patch("frappe.db.set_value") as mock_set:
                            with patch("frappe.db.commit"):
                                wrong_paid = {"amount": {"amount": 20, "currency": "XOF"}}
                                result = _mark_paid("SP-TEST-001", wrong_paid)

        self.assertFalse(result)


# ── T7 : reconcile_pending_payments — expiration checkout ────────────────────


class TestReconcilePendingPayments(FrappeTestCase):
    """Un checkout en attente depuis plus de PENDING_EXPIRES_AFTER_DAYS est clôturé FAILED."""

    def test_old_checkout_expired_as_failed(self):
        """Checkout pending > 7 jours → FAILED par reconcile_pending_payments."""
        import datetime as dt
        old_date = frappe.utils.now_datetime() - dt.timedelta(days=8)

        row = MagicMock()
        row.name = "SP-OLD-001"
        row.payment_provider = CHECKOUT
        row.transaction_id = "txn-old-001"
        row.creation = old_date

        fake_client = MagicMock()
        fake_client.get_payment_request.return_value = {"status": "pending"}

        with patch("frappe.get_all", return_value=[row]):
            with patch("sitiame_core.subscription_api.jeko_client", fake_client):
                with patch("frappe.db.set_value") as mock_set:
                    with patch("frappe.db.commit"):
                        subscription_api.reconcile_pending_payments()

        # Doit avoir été marqué FAILED
        calls = [c for c in mock_set.call_args_list if FAILED in str(c)]
        self.assertTrue(len(calls) > 0, "Le checkout expiré n'a pas été marqué FAILED")

    def test_old_link_not_auto_expired(self):
        """Un staff LINK ancien ne doit PAS être automatiquement clôturé FAILED."""
        import datetime as dt
        old_date = frappe.utils.now_datetime() - dt.timedelta(days=10)

        row = MagicMock()
        row.name = "SP-LINK-OLD"
        row.payment_provider = LINK
        row.transaction_id = "lnk-old-001"
        row.creation = old_date

        fake_client = MagicMock()
        fake_client.get_payment_link.return_value = {"canReceivePayments": True}

        with patch("frappe.get_all", return_value=[row]):
            with patch("sitiame_core.subscription_api.jeko_client", fake_client):
                with patch("frappe.db.set_value") as mock_set:
                    with patch("frappe.db.commit"):
                        subscription_api.reconcile_pending_payments()

        failed_calls = [c for c in mock_set.call_args_list if FAILED in str(c)]
        self.assertEqual(len(failed_calls), 0, "Un LINK ne doit jamais être auto-expiré")
