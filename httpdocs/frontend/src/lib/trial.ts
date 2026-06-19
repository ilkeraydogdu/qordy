import { getBootstrap } from "@/lib/bootstrap";

export function getTrialDays(): number {
  return getBootstrap().trial.duration_days;
}

export function trialLabel(short = false): string {
  const days = getTrialDays();
  return short ? `${days} gün` : `${days} gün ücretsiz`;
}
