import { useEffect, useState, type FormEvent } from 'react'
import { extractError, fetchStudents, type StudentListItem } from '../../api/admin'
import {
  acceptInvitation,
  createProject,
  declineInvitation,
  fetchInvitations,
  fetchTeam,
  inviteMember,
  removeMember,
  transferLead,
  type Invitation,
  type Team,
} from '../../api/team'
import { useAuth } from '../../contexts/AuthContext'

/**
 * Forming a project and a team.
 *
 * The first step of the Sprint 3 chain: a project exists before it has a
 * supervisor, so this page comes before the supervisor directory.
 */
export function StudentTeamPage() {
  const { user } = useAuth()
  const [team, setTeam] = useState<Team | null>(null)
  const [invitations, setInvitations] = useState<Invitation[]>([])
  const [classmates, setClassmates] = useState<StudentListItem[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)

  const [projectForm, setProjectForm] = useState({ title: '', is_team: true, team_name: '' })
  const [inviteId, setInviteId] = useState('')
  const [inviteMessage, setInviteMessage] = useState('')

  async function load() {
    setIsLoading(true)
    try {
      const [teamData, invites] = await Promise.all([fetchTeam(), fetchInvitations()])
      setTeam(teamData)
      setInvitations(invites)
    } catch (err) {
      setError(extractError(err, 'Could not load your team.'))
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    void load()
    // Classmates to invite. Falls back silently — a student may not be able to
    // list others, in which case they can still be invited by id.
    void fetchStudents({}).then((page) => setClassmates(page.data)).catch(() => undefined)
  }, [])

  function announce(message: string) {
    setSuccess(message)
    setError(null)
  }

  async function handleCreate(event: FormEvent) {
    event.preventDefault()
    setError(null)

    try {
      await createProject({
        title: projectForm.title,
        is_team: projectForm.is_team,
        team_name: projectForm.team_name || undefined,
      })
      announce(
        projectForm.is_team
          ? 'Team created. Invite members, then request a supervisor.'
          : 'Individual project created. You can request a supervisor now.',
      )
      await load()
    } catch (err) {
      setError(extractError(err, 'Could not create the project.'))
    }
  }

  async function handleInvite(event: FormEvent) {
    event.preventDefault()
    setError(null)

    try {
      await inviteMember({
        student_id: Number(inviteId),
        message: inviteMessage || undefined,
      })
      setInviteId('')
      setInviteMessage('')
      announce('Invitation sent.')
      await load()
    } catch (err) {
      setError(extractError(err, 'Could not send the invitation.'))
    }
  }

  async function respond(invitation: Invitation, accept: boolean) {
    setError(null)

    try {
      if (accept) {
        await acceptInvitation(invitation.id)
        announce('You have joined the team.')
      } else {
        await declineInvitation(invitation.id)
        announce('Invitation declined.')
      }
      await load()
    } catch (err) {
      setError(extractError(err, 'Could not respond to the invitation.'))
    }
  }

  async function handleRemove(studentId: number, isSelf: boolean) {
    if (!window.confirm(isSelf ? 'Leave this team?' : 'Remove this member?')) {
      return
    }

    setError(null)

    try {
      await removeMember(studentId)
      announce(isSelf ? 'You have left the team.' : 'Member removed.')
      await load()
    } catch (err) {
      setError(extractError(err, 'Could not update the team.'))
    }
  }

  async function handleTransfer(studentId: number) {
    setError(null)

    try {
      await transferLead(studentId)
      announce('Team lead transferred.')
      await load()
    } catch (err) {
      setError(extractError(err, 'Could not transfer the lead.'))
    }
  }

  const openInvitations = invitations.filter((invitation) => invitation.is_actionable)

  return (
    <div className="space-y-6 animate-[fadeRise_500ms_ease-out]">
      <header>
        <h1 className="font-[family-name:var(--font-display)] text-4xl text-[var(--color-sea-deep)]">
          My team
        </h1>
        <p className="mt-2 max-w-2xl text-[var(--color-ink-muted)]">
          Start a project on your own or with a team, then ask a supervisor to
          take it on.
        </p>
      </header>

      {error && <p className="text-[var(--color-danger)]">{error}</p>}
      {success && <p className="text-[var(--color-success)]">{success}</p>}

      {/* Invitations first: someone is waiting on an answer. */}
      {openInvitations.length > 0 && (
        <section className="rounded-xl border border-[var(--color-sea)] bg-[var(--color-sea-soft)] p-5">
          <h2 className="font-semibold text-[var(--color-sea-deep)]">
            You have been invited to a team
          </h2>
          <ul className="mt-3 grid gap-3">
            {openInvitations.map((invitation) => (
              <li
                key={invitation.id}
                className="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-[var(--color-surface)] px-4 py-3"
              >
                <span className="min-w-0">
                  <span className="font-medium">{invitation.team.name}</span>
                  <span className="block text-sm text-[var(--color-ink-muted)]">
                    {invitation.invited_by} · {invitation.team.member_count} member
                    {invitation.team.member_count === 1 ? '' : 's'}
                    {invitation.message && ` · “${invitation.message}”`}
                  </span>
                </span>
                <span className="flex gap-3 text-sm">
                  <button
                    type="button"
                    onClick={() => void respond(invitation, true)}
                    className="rounded-lg bg-[var(--color-sea)] px-3 py-2 font-semibold text-[var(--color-on-brand)]"
                  >
                    Accept
                  </button>
                  <button
                    type="button"
                    onClick={() => void respond(invitation, false)}
                    className="text-[var(--color-danger)] hover:underline"
                  >
                    Decline
                  </button>
                </span>
              </li>
            ))}
          </ul>
        </section>
      )}

      {isLoading ? (
        <p className="text-[var(--color-ink-muted)]">Loading…</p>
      ) : team === null ? (
        <section className="max-w-lg rounded-xl border border-[var(--color-paper-deep)] bg-[var(--color-surface)] p-6">
          <h2 className="font-[family-name:var(--font-display)] text-2xl text-[var(--color-sea-deep)]">
            Start a project
          </h2>
          <p className="mt-2 text-sm text-[var(--color-ink-muted)]">
            You do not have a team yet. Starting a team project makes you its
            lead.
          </p>

          <form onSubmit={handleCreate} className="mt-4 grid gap-3">
            <input
              required
              placeholder="Project title"
              value={projectForm.title}
              onChange={(e) => setProjectForm((p) => ({ ...p, title: e.target.value }))}
              className="rounded-lg border border-[var(--color-paper-deep)] px-3 py-2.5"
            />

            <label className="flex items-center gap-2 text-sm">
              <input
                type="checkbox"
                checked={projectForm.is_team}
                onChange={(e) => setProjectForm((p) => ({ ...p, is_team: e.target.checked }))}
              />
              <span>This is a team project</span>
            </label>

            {projectForm.is_team && (
              <input
                placeholder="Team name (defaults to the project title)"
                value={projectForm.team_name}
                onChange={(e) => setProjectForm((p) => ({ ...p, team_name: e.target.value }))}
                className="rounded-lg border border-[var(--color-paper-deep)] px-3 py-2.5"
              />
            )}

            <button
              type="submit"
              className="rounded-lg bg-[var(--color-sea)] px-4 py-2.5 font-semibold text-[var(--color-on-brand)] transition hover:bg-[var(--color-sea-deep)]"
            >
              Create project
            </button>
          </form>
        </section>
      ) : (
        <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
          <section className="rounded-xl border border-[var(--color-paper-deep)] bg-[var(--color-surface)]/70 p-5">
            <div className="flex flex-wrap items-center justify-between gap-3">
              <h2 className="font-semibold">{team.name}</h2>
              <span className="text-sm text-[var(--color-ink-muted)] tabular-nums">
                {team.member_count} of {team.min_members}–{team.max_members} members
              </span>
            </div>

            {team.member_count < team.min_members && (
              <p className="mt-2 rounded-lg bg-[var(--tint-amber)] px-3 py-2 text-sm text-[var(--color-amber)]">
                A team needs at least {team.min_members} members before it can
                request a supervisor.
              </p>
            )}

            {team.supervisor && (
              <p className="mt-2 text-sm text-[var(--color-success)]">
                Supervised by {team.supervisor.name}.
              </p>
            )}

            <ul className="mt-4 grid gap-2">
              {team.members.map((member) => {
                const isSelf = member.name === user?.name

                return (
                  <li
                    key={member.student_id}
                    className="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-[var(--color-paper)] px-3 py-2 text-sm"
                  >
                    <span>
                      <span className="font-medium">{member.name}</span>
                      {member.is_leader && (
                        <span className="ml-2 inline-flex rounded-md bg-[var(--color-sea-soft)] px-2 py-0.5 text-xs font-semibold text-[var(--color-sea-deep)]">
                          Lead
                        </span>
                      )}
                      <span className="block text-[var(--color-ink-muted)]">
                        {member.registration_number}
                      </span>
                    </span>

                    <span className="flex gap-3">
                      {team.is_lead && !member.is_leader && (
                        <button
                          type="button"
                          onClick={() => void handleTransfer(member.student_id)}
                          className="text-[var(--color-sea)] hover:underline"
                        >
                          Make lead
                        </button>
                      )}
                      {(team.is_lead || isSelf) && !member.is_leader && (
                        <button
                          type="button"
                          onClick={() => void handleRemove(member.student_id, isSelf)}
                          className="text-[var(--color-danger)] hover:underline"
                        >
                          {isSelf ? 'Leave' : 'Remove'}
                        </button>
                      )}
                    </span>
                  </li>
                )
              })}
            </ul>

            {team.pending_invitations.length > 0 && (
              <>
                <h3 className="mt-5 text-sm font-semibold">Awaiting a reply</h3>
                <ul className="mt-2 grid gap-1 text-sm text-[var(--color-ink-muted)]">
                  {team.pending_invitations.map((invite) => (
                    <li key={invite.id}>
                      {invite.name} ({invite.registration_number})
                    </li>
                  ))}
                </ul>
              </>
            )}
          </section>

          {team.is_lead && team.member_count < team.max_members && (
            <section className="rounded-xl border border-[var(--color-paper-deep)] bg-[var(--color-surface)] p-5">
              <h2 className="font-semibold">Invite a member</h2>
              <p className="mt-1 text-sm text-[var(--color-ink-muted)]">
                They join by accepting — invitations expire if left unanswered.
              </p>

              <form onSubmit={handleInvite} className="mt-4 grid gap-3">
                <select
                  required
                  value={inviteId}
                  onChange={(e) => setInviteId(e.target.value)}
                  className="rounded-lg border border-[var(--color-paper-deep)] px-3 py-2.5"
                >
                  <option value="">Choose a classmate</option>
                  {classmates.map((student) => (
                    <option key={student.id} value={student.id}>
                      {student.name} ({student.registration_number})
                    </option>
                  ))}
                </select>

                <textarea
                  rows={2}
                  placeholder="Message (optional)"
                  value={inviteMessage}
                  onChange={(e) => setInviteMessage(e.target.value)}
                  className="rounded-lg border border-[var(--color-paper-deep)] px-3 py-2.5"
                />

                <button
                  type="submit"
                  className="rounded-lg bg-[var(--color-sea)] px-4 py-2.5 font-semibold text-[var(--color-on-brand)] transition hover:bg-[var(--color-sea-deep)]"
                >
                  Send invitation
                </button>
              </form>
            </section>
          )}
        </div>
      )}
    </div>
  )
}
